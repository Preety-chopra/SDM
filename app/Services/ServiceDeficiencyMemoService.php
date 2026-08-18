<?php

namespace App\Services;

use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Models\Contractor;
use App\Models\MemoAttachment;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ServiceDeficiencyMemoService
{
    public function __construct(private ServiceDeficiencyMemoRepositoryInterface $memos) {}

    public function paginateFor(User $user): LengthAwarePaginator
    {
        return $this->memos->paginateFor($user);
    }

    public function createDraft(User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist(null, $user, $data, false);
    }

    public function createSubmitted(User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist(null, $user, $data, true);
    }

    public function updateDraft(ServiceDeficiencyMemo $memo, User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist($memo, $user, $data, false);
    }

    public function submit(ServiceDeficiencyMemo $memo, User $user, array $data): ServiceDeficiencyMemo
    {
        return $this->persist($memo, $user, $data, true);
    }

    public function deleteAttachment(MemoAttachment $attachment): void
    {
        DB::transaction(fn () => $attachment->delete());
        Storage::disk('local')->delete($attachment->storage_path);
    }

    private function persist(?ServiceDeficiencyMemo $memo, User $user, array $data, bool $submit): ServiceDeficiencyMemo
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($memo, $user, $data, $submit, &$storedPaths): ServiceDeficiencyMemo {
                $station = Station::query()->with('metroLine')->whereKey($user->station_id)->where('is_active', true)->firstOrFail();
                abort_unless($station->metroLine?->is_active, 422, 'Your station must be assigned to an active line.');
                $contractor = filled($data['contractor_id'] ?? null) ? Contractor::query()->whereKey($data['contractor_id'])->firstOrFail() : null;

                $attributes = [
                    'service_type' => $data['service_type'],
                    'serial_number' => $data['serial_number'],
                    'station_id' => $station->id,
                    'station_name' => $station->name,
                    'line_name' => $station->metroLine->name,
                    'contractor_id' => $contractor?->id,
                    'contractor_name' => $contractor?->name,
                    'staff_name' => $data['staff_name'] ?? null,
                    'employee_number' => $data['employee_number'] ?? null,
                    'incident_date' => filled($data['incident_date'] ?? null) ? CarbonImmutable::createFromFormat('d-m-Y', $data['incident_date'])->toDateString() : null,
                    'updated_by' => $user->id,
                ];

                if ($submit) {
                    $attributes['status'] = MemoStatus::Submitted->value;
                    $attributes['submitted_at'] = now();
                }

                if ($memo === null) {
                    $attributes['created_by'] = $user->id;
                    $memo = $this->memos->create($attributes);
                } else {
                    $memo = $this->memos->update($memo, $attributes);
                }

                $this->syncSections($memo, $data);
                foreach (Arr::wrap($data['attachments'] ?? []) as $file) {
                    if ($file instanceof UploadedFile) {
                        $path = $file->store('sdm-memos/'.$memo->id, 'local');
                        $storedPaths[] = $path;
                        $memo->attachments()->create([
                            'original_name' => $file->getClientOriginalName(),
                            'storage_path' => $path,
                            'mime_type' => $file->getMimeType(),
                            'size_bytes' => $file->getSize(),
                            'uploaded_by' => $user->id,
                        ]);
                    }
                }

                return $memo->load(['deficiencies.lateReporting', 'publicComplaint.station', 'cashCheck', 'attachments']);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
    }

    private function syncSections(ServiceDeficiencyMemo $memo, array $data): void
    {
        $memo->deficiencies()->delete();
        foreach ($data['deficiencies'] ?? [] as $type) {
            $deficiency = $memo->deficiencies()->create([
                'type' => $type,
                'remarks' => $data['deficiency_remarks'][$type] ?? null,
            ]);
            if ($type === DeficiencyType::LateReporting->value && filled($data['late_reporting'] ?? null)) {
                $deficiency->lateReporting()->create($data['late_reporting']);
            }
        }

        $memo->publicComplaint()->delete();
        if (filled($data['public_complaint']['number'] ?? null)) {
            $complaintStation = Station::query()->findOrFail($data['public_complaint']['station_id']);
            $memo->publicComplaint()->create([
                'complaint_serial_number' => $data['public_complaint']['number'],
                'station_id' => $complaintStation->id,
                'station_name' => $complaintStation->name,
            ]);
        }

        $memo->cashCheck()->delete();
        $cashSelected = in_array(DeficiencyType::CashCheck->value, $data['deficiencies'] ?? [], true);
        if (SdmType::from($data['service_type']) === SdmType::Ticketing && $cashSelected) {
            $cash = Arr::except($data['cash_check'] ?? [], ['check_date', 'check_time']);
            $checkDate = $data['cash_check']['check_date'] ?? null;
            $checkTime = $data['cash_check']['check_time'] ?? null;
            $cash['checked_at'] = filled($checkDate) && filled($checkTime)
                ? CarbonImmutable::createFromFormat('d-m-Y H:i', "{$checkDate} {$checkTime}")
                : null;
            $memo->cashCheck()->create($cash);
        }
    }
}
