<?php

namespace App\Services;

use App\Enums\MemoStatus;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemoPenaltyLifecycleService
{
    public function __construct(
        private AuditService $audit,
        private MemoWorkflowService $workflow,
    ) {}

    public function issueLetter(ServiceDeficiencyMemo $memo, User $actor, array $data): ServiceDeficiencyMemo
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($memo, $actor, $data, &$storedPath): ServiceDeficiencyMemo {
                $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
                if ($memo->status !== MemoStatus::PendingPenaltyLetter) {
                    throw ValidationException::withMessages(['letter' => 'This SDM is not awaiting a penalty letter.']);
                }

                $assessment = $memo->assessments()->where('status', 'approved')->reorder('version', 'desc')->firstOrFail();
                /** @var UploadedFile $file */
                $file = $data['letter'];
                $revision = ((int) $memo->penaltyLetters()->max('revision')) + 1;
                $storedPath = $file->store("sdm-memos/{$memo->id}/penalty-letters", 'local');
                $memo->penaltyLetters()->where('is_current', true)->update(['is_current' => false]);
                $issuedAt = Carbon::parse($data['issued_at'])->startOfDay();
                $letter = $memo->penaltyLetters()->create([
                    'memo_assessment_id' => $assessment->id,
                    'revision' => $revision,
                    'issued_at' => $issuedAt,
                    'objection_due_at' => $issuedAt->copy()->addDays(15),
                    'delivery_details' => $data['delivery_details'],
                    'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $storedPath,
                    'mime_type' => $file->getMimeType(),
                    'size_bytes' => $file->getSize(),
                    'uploaded_by' => $actor->id,
                    'is_current' => true,
                ]);
                $memo->update(['status' => MemoStatus::ObjectionWindow->value]);
                $this->audit->record($memo, $actor, 'memo.penalty_letter.issued', [], [
                    'letter_id' => $letter->id,
                    'revision' => $revision,
                    'issued_at' => $issuedAt->toDateString(),
                    'objection_due_at' => $letter->objection_due_at->toDateString(),
                ]);

                return $memo->refresh()->load('currentPenaltyLetter');
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function recordObjection(ServiceDeficiencyMemo $memo, User $actor, array $data): ServiceDeficiencyMemo
    {
        $storedPath = null;

        try {
            return DB::transaction(function () use ($memo, $actor, $data, &$storedPath): ServiceDeficiencyMemo {
                $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
                if ($memo->status !== MemoStatus::ObjectionWindow) {
                    throw ValidationException::withMessages(['received_at' => 'This SDM is not in an objection window.']);
                }
                $letter = $memo->currentPenaltyLetter()->lockForUpdate()->firstOrFail();
                $receivedAt = Carbon::parse($data['received_at'])->startOfDay();
                if ($receivedAt->lt($letter->issued_at) || $receivedAt->gt($letter->objection_due_at)) {
                    throw ValidationException::withMessages(['received_at' => 'The objection receipt date must fall within the current 15-day window.']);
                }

                $attributes = [
                    'memo_penalty_letter_id' => $letter->id,
                    'received_at' => $receivedAt,
                    'remarks' => $data['remarks'],
                    'recorded_by' => $actor->id,
                ];
                if (($data['attachment'] ?? null) instanceof UploadedFile) {
                    $file = $data['attachment'];
                    $storedPath = $file->store("sdm-memos/{$memo->id}/objections", 'local');
                    $attributes += [
                        'original_name' => $file->getClientOriginalName(),
                        'storage_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'size_bytes' => $file->getSize(),
                    ];
                }
                $objection = $memo->objections()->create($attributes);
                $this->workflow->createReviewRound($memo);
                $memo->update(['status' => MemoStatus::ObjectionReview->value]);
                $this->audit->record($memo, $actor, 'memo.objection.recorded', [], [
                    'objection_id' => $objection->id,
                    'letter_id' => $letter->id,
                    'received_at' => $receivedAt->toDateString(),
                ]);

                return $memo->refresh()->load('currentWorkflowStep');
            });
        } catch (Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }
    }

    public function confirmNoObjection(ServiceDeficiencyMemo $memo, User $actor): ServiceDeficiencyMemo
    {
        return DB::transaction(function () use ($memo, $actor): ServiceDeficiencyMemo {
            $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
            $letter = $memo->currentPenaltyLetter()->firstOrFail();
            if ($memo->status !== MemoStatus::ObjectionWindow || ! now()->startOfDay()->gt($letter->objection_due_at)) {
                throw ValidationException::withMessages(['objection' => 'The objection window has not expired.']);
            }
            if ($letter->objection()->exists()) {
                throw ValidationException::withMessages(['objection' => 'An objection has already been recorded for this letter.']);
            }

            $memo->update(['status' => MemoStatus::PendingDeduction->value]);
            $this->audit->record($memo, $actor, 'memo.objection_window.expired', [], ['letter_id' => $letter->id]);

            return $memo->refresh();
        });
    }

    public function recordDeduction(ServiceDeficiencyMemo $memo, User $actor, array $data): ServiceDeficiencyMemo
    {
        return DB::transaction(function () use ($memo, $actor, $data): ServiceDeficiencyMemo {
            $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
            if ($memo->status !== MemoStatus::PendingDeduction) {
                throw ValidationException::withMessages(['amount' => 'This SDM is not awaiting deduction.']);
            }
            $approved = $memo->assessments()->where('status', 'approved')->reorder('version', 'desc')->firstOrFail();
            if (abs((float) $approved->proposed_total - (float) $data['amount']) > 0.009) {
                throw ValidationException::withMessages(['amount' => 'The deduction must exactly match the approved amount.']);
            }

            $deduction = $memo->deduction()->create([
                'bill_month' => Carbon::createFromFormat('Y-m', $data['bill_month'])->startOfMonth(),
                'bill_reference' => $data['bill_reference'],
                'deducted_at' => $data['deducted_at'],
                'amount' => $data['amount'],
                'recorded_by' => $actor->id,
            ]);
            $memo->update(['status' => MemoStatus::Closed->value]);
            $this->audit->record($memo, $actor, 'memo.deduction.recorded', [], [
                'deduction_id' => $deduction->id,
                'bill_month' => $deduction->bill_month->format('Y-m'),
                'bill_reference' => $deduction->bill_reference,
                'deducted_at' => $deduction->deducted_at->toDateString(),
                'amount' => $deduction->amount,
            ]);

            return $memo->refresh()->load('deduction');
        });
    }
}
