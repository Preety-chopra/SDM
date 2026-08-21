<?php

namespace App\Services;

use App\Enums\MemoStatus;
use App\Enums\WorkflowStage;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class MemoWorkflowService
{
    public function act(ServiceDeficiencyMemo $memo, User $actor, array $data): ServiceDeficiencyMemo
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($memo, $actor, $data, &$storedPaths): ServiceDeficiencyMemo {
                $memo = ServiceDeficiencyMemo::query()->lockForUpdate()->findOrFail($memo->id);
                $stage = $memo->current_stage;
                $approval = $memo->approvals()->where('role', $stage->value)->lockForUpdate()->firstOrFail();
                $action = $data['action'];

                if ($approval->assigned_to !== $actor->id || $approval->decision !== 'pending') {
                    abort(403);
                }
                if ($stage === WorkflowStage::DeputyGeneralManager && $action === 'forward') {
                    throw ValidationException::withMessages(['action' => 'DGM/O must approve or cancel the SDM.']);
                }
                if ($stage !== WorkflowStage::DeputyGeneralManager && $action === 'approve') {
                    throw ValidationException::withMessages(['action' => 'This stage must forward or cancel the SDM.']);
                }

                $approval->update([
                    'decision' => $action === 'cancel' ? 'rejected' : 'approved',
                    'acted_by' => $actor->id,
                    'signer_name' => $actor->name,
                    'signer_employee_number' => $actor->employee_number,
                    'acted_at' => now(),
                    'remarks' => $data['remarks'] ?? null,
                ]);

                foreach (Arr::wrap($data['evidence'] ?? []) as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store("sdm-memos/{$memo->id}/workflow/{$approval->id}", 'local');
                        $storedPaths[] = $path;
                        $approval->attachments()->create([
                            'original_name' => $file->getClientOriginalName(),
                            'storage_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => $actor->id,
                        ]);
                    }
                }

                if ($action === 'cancel') {
                    $memo->update(['status' => MemoStatus::Cancelled->value]);
                } elseif ($action === 'approve') {
                    $memo->update(['status' => MemoStatus::Closed->value]);
                } else {
                    $memo->update([
                        'status' => MemoStatus::UnderReview->value,
                        'current_stage' => $stage->next()->value,
                    ]);
                }

                return $memo->refresh()->load(['approvals.assignee', 'approvals.actor', 'approvals.attachments']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }
}
