<?php

namespace App\Services;

use App\Contracts\Repositories\HistoricalSdmRepositoryInterface;
use App\Models\User;
use App\Support\HistoricalSdmWorkbookReader;
use App\Support\OutsourcedStaffIdentifier;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class HistoricalSdmImportService
{
    public function __construct(
        private HistoricalSdmWorkbookReader $reader,
        private HistoricalSdmRepositoryInterface $records,
        private AuditService $audit,
    ) {}

    public function import(string $path, int $contractorId, User $actor, bool $dryRun = false, string $sheet = 'previous records of JMD'): array
    {
        Validator::make(['contractor_id' => $contractorId], ['contractor_id' => ['required', 'integer', 'exists:contractors,id']])->validate();
        $rows = $this->reader->read($path, $sheet);
        $sha256 = hash_file('sha256', $path);

        return DB::transaction(function () use ($rows, $path, $sha256, $contractorId, $actor, $dryRun, $sheet): array {
            $this->records->lockContractor($contractorId);
            $existing = $this->records->findImport($contractorId, $sha256, $sheet);
            if ($existing !== null) {
                return ['created' => 0, 'skipped' => $existing->row_count, 'import_id' => $existing->id, 'issues' => $existing->summary];
            }
            $staff = $this->records->staff($contractorId);
            $employees = $staff->groupBy(fn ($person): string => mb_strtoupper($person->employee_id));
            $aadhaarMatches = $staff->whereNotNull('aadhaar_lookup_hash')->groupBy('aadhaar_lookup_hash');
            $stations = [];
            foreach ($this->records->stations() as $station) {
                $line = $this->line($station->line_code ?? $station->metroLine?->code ?? '');
                foreach (array_unique([$station->code, $station->station_code]) as $code) {
                    if ($code !== null && $code !== '') {
                        $stations[$line.'|'.mb_strtoupper($code)][$station->id] = $station->id;
                    }
                }
            }
            $prepared = [];
            $summary = [];
            foreach ($rows as $row) {
                $v = $row['values'];
                $issues = [];
                $date = $this->date($v[2]);
                if ($date === null) {
                    $issues[] = 'invalid_date';
                }
                $aadhaar = OutsourcedStaffIdentifier::aadhaar($v[8]);
                if ($aadhaar === null && $v[8] !== '') {
                    $issues[] = 'invalid_aadhaar';
                }
                $hash = OutsourcedStaffIdentifier::hash($aadhaar);
                $candidates = $v[7] !== '' ? $employees->get(mb_strtoupper($v[7]), collect()) : $aadhaarMatches->get($hash ?? '', collect());
                $person = $candidates->count() === 1 ? $candidates->first() : null;
                if ($person === null) {
                    $issues[] = $candidates->count() > 1 ? 'staff_ambiguous' : 'staff_unmatched';
                } elseif ($hash !== null && $person->aadhaar_lookup_hash !== null && ! hash_equals($person->aadhaar_lookup_hash, $hash)) {
                    $person = null;
                    $issues[] = 'staff_identity_conflict';
                }
                $line = $this->line($v[4]);
                $code = mb_strtoupper($v[5]);
                // An explicit interchange suffix (LJPN-06) may be removed only for that same source line.
                if (preg_match('/^(.+)-0?([0-9]+)$/D', $code, $matches) && $matches[2] === $line) {
                    $code = $matches[1];
                }
                $matches = array_values($stations[$line.'|'.$code] ?? []);
                $stationId = count($matches) === 1 ? $matches[0] : null;
                if ($stationId === null) {
                    $issues[] = count($matches) > 1 ? 'station_ambiguous' : 'station_unmatched';
                }
                $attributes = [
                    'contractor_id' => $contractorId, 'outsourced_staff_id' => $person?->id, 'station_id' => $stationId,
                    'source_row' => $row['number'], 'source_usn' => $v[0], 'source_date' => $v[2], 'incident_date' => $date,
                    'memo_number' => $v[3], 'source_line' => $v[4], 'source_station' => $v[5],
                    'employee_name' => $v[6], 'employee_number' => $v[7], 'aadhaar_number' => $v[8],
                    'discrepancies' => $v[9], 'recommended_action' => $v[10], 'contractor_penalty' => $v[11],
                    'cp_number' => $v[13], 'negative_points' => $v[15], 'import_issues' => $issues,
                ];
                $attributes = array_map(fn ($value) => $value === '' ? null : $value, $attributes);
                $validator = Validator::make($attributes, [
                    'source_usn' => ['nullable', 'string', 'max:100'], 'source_date' => ['nullable', 'string', 'max:100'],
                    'memo_number' => ['nullable', 'string', 'max:255'], 'source_line' => ['nullable', 'string', 'max:30'],
                    'source_station' => ['nullable', 'string', 'max:100'], 'employee_name' => ['nullable', 'string', 'max:255'],
                    'employee_number' => ['nullable', 'string', 'max:100'], 'aadhaar_number' => ['nullable', 'string', 'max:255'],
                    'discrepancies' => ['nullable', 'string', 'max:15000'], 'recommended_action' => ['nullable', 'string', 'max:15000'],
                    'contractor_penalty' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
                    'negative_points' => ['nullable', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
                    'cp_number' => ['nullable', 'string', 'max:255'],
                ]);
                if ($validator->fails()) {
                    throw ValidationException::withMessages(['file' => 'Row '.$row['number'].': '.$validator->errors()->first()]);
                }
                foreach ($issues as $issue) {
                    $summary[$issue] = ($summary[$issue] ?? 0) + 1;
                }
                $prepared[] = $attributes;
            }
            $result = ['created' => count($prepared), 'skipped' => 0, 'import_id' => null, 'issues' => $summary];
            if ($dryRun) {
                return $result;
            }
            $import = $this->records->createImport([
                'contractor_id' => $contractorId, 'imported_by' => $actor->id, 'source_file' => basename($path),
                'source_sha256' => $sha256, 'source_sheet' => $sheet, 'row_count' => count($prepared), 'summary' => $summary,
            ]);
            $this->records->insertRecords(array_map(fn (array $attributes): array => ['import_id' => $import->id, ...$attributes], $prepared));
            $this->audit->record($import, $actor, 'historical_sdm.imported', [], [
                'contractor_id' => $contractorId, 'source_sha256' => $sha256, 'source_sheet' => $sheet,
                'row_count' => count($prepared), 'issues' => $summary,
            ]);
            $result['import_id'] = $import->id;

            return $result;
        });
    }

    private function line(string $value): string
    {
        $value = mb_strtoupper(trim($value));
        if (preg_match('/^(?:L-)?([1-9]|10)(?:[ -]?[EWNS])?$/D', $value, $matches)) {
            return $matches[1];
        }

        return $value;
    }

    private function date(string $value): ?string
    {
        if (is_numeric($value) && (float) $value >= 1 && (float) $value <= 2958465) {
            return (new DateTimeImmutable('1899-12-30'))->modify('+'.(int) $value.' days')->format('Y-m-d');
        }
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
            if ($date !== false && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
