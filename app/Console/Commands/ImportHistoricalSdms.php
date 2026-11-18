<?php

namespace App\Console\Commands;

use App\Enums\UserPermission;
use App\Models\User;
use App\Services\HistoricalSdmImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('sdm:import-history {file : Path to the historical XLSX workbook} {--contractor= : Existing contractor ID} {--actor= : Enabled user ID with contractor management permission} {--sheet=previous records of JMD : Worksheet to import} {--dry-run : Validate and report without writing}')]
#[Description('Import historical SDMs with source provenance, master links, encrypted Aadhaar and audit logging')]
class ImportHistoricalSdms extends Command
{
    public function handle(HistoricalSdmImportService $importer): int
    {
        $actor = User::query()->find($this->option('actor'));
        if (! $actor || ! $actor->is_enabled || ! $actor->can(UserPermission::ManageContractors->value)) {
            $this->error('Specify an enabled --actor user with contractor management permission.');

            return self::FAILURE;
        }
        if (! ctype_digit((string) $this->option('contractor'))) {
            $this->error('Specify an existing --contractor ID.');

            return self::FAILURE;
        }
        try {
            $result = $importer->import($this->argument('file'), (int) $this->option('contractor'), $actor, (bool) $this->option('dry-run'), $this->option('sheet'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $prefix = $this->option('dry-run') ? 'Dry run' : 'Import complete';
        $this->info("{$prefix}: {$result['created']} historical SDMs; {$result['skipped']} previously imported rows skipped.");
        foreach ($result['issues'] as $issue => $count) {
            $this->warn("{$issue}: {$count} rows (source values retained).");
        }
        if ($result['import_id'] !== null) {
            $this->info('Import ID: '.$result['import_id']);
        }

        return self::SUCCESS;
    }
}
