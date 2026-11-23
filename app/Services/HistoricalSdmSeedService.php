<?php

namespace App\Services;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class HistoricalSdmSeedService
{
    public function __construct(
        private MasterDataRepositoryInterface $masterData,
        private HistoricalSdmImportService $importer,
    ) {}

    public function seed(mixed $manifest): array
    {
        $manifest = Validator::make(['manifest' => $manifest], [
            'manifest' => ['required', 'array'],
            'manifest.version' => ['required', 'integer', 'in:1'],
            'manifest.imports' => ['required', 'array', 'list', 'min:1'],
            'manifest.imports.*' => ['required', 'array'],
            'manifest.imports.*.contractor_name' => ['required', 'string', 'max:255'],
            'manifest.imports.*.file' => ['required', 'string', 'max:255', 'regex:/\A[A-Za-z0-9][A-Za-z0-9 ._-]*\.xlsx\z/i'],
            'manifest.imports.*.sheet' => ['required', 'string', 'max:100'],
            'manifest.imports.*.sha256' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'manifest.imports.*.row_count' => ['required', 'integer', 'min:1', 'max:25000'],
        ])->validate()['manifest'];

        return DB::transaction(function () use ($manifest): array {
            $contractors = $this->masterData->contractors()->groupBy(fn ($contractor): string => mb_strtolower($contractor->name));
            $disk = Storage::disk('local');
            $result = ['created' => 0, 'skipped' => 0, 'issues' => []];

            foreach ($manifest['imports'] as $entry) {
                $matches = $contractors->get(mb_strtolower($entry['contractor_name']), collect());
                if ($matches->count() !== 1) {
                    throw ValidationException::withMessages(['contractor_name' => 'Each historical workbook must match exactly one existing contractor by name. Seed the contractor master data first.']);
                }

                $path = $disk->path('seed-data/'.$entry['file']);
                if (! is_file($path) || ! is_readable($path)) {
                    throw ValidationException::withMessages(['file' => 'Upload every historical XLSX workbook listed in the manifest to storage/app/private/seed-data before running HistoricalSdmSeeder.']);
                }
                if (! hash_equals($entry['sha256'], hash_file('sha256', $path))) {
                    throw ValidationException::withMessages(['file' => 'A historical workbook checksum does not match the manifest. Upload the original unmodified workbook.']);
                }

                $import = $this->importer->import($path, $matches->sole()->id, null, sheet: $entry['sheet']);
                if ((int) $entry['row_count'] !== $import['created'] + $import['skipped']) {
                    throw ValidationException::withMessages(['row_count' => 'The historical workbook row count does not match the manifest. The entire seed has been rolled back.']);
                }

                $result['created'] += $import['created'];
                $result['skipped'] += $import['skipped'];
                foreach ($import['issues'] as $issue => $count) {
                    $result['issues'][$issue] = ($result['issues'][$issue] ?? 0) + $count;
                }
            }

            return $result;
        });
    }
}
