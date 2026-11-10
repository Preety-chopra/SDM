<?php

namespace App\Console\Commands;

use App\Enums\UserPermission;
use App\Models\User;
use App\Services\OutsourcedStaffImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('outsourced-staff:import {file : Path to the source XLSX workbook} {--actor= : Enabled user ID for audit attribution} {--agency=* : Agency code=existing contractor ID} {--invalid-aadhaar=reject : reject or blank} {--dry-run : Validate without writing any data}')]
#[Description('Import outsourced staff, combining DOB columns and skipping existing employee IDs')]
class ImportOutsourcedStaff extends Command
{
    public function handle(OutsourcedStaffImportService $importer): int
    {
        $actor = User::query()->find($this->option('actor'));
        if (! $actor || ! $actor->is_enabled || ! $actor->can(UserPermission::ManageContractors->value)) {
            $this->error('Specify an enabled --actor user with contractor management permission.');

            return self::FAILURE;
        }
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 10 * 1024 * 1024) {
            $this->error('Specify a readable XLSX file no larger than 10 MB.');

            return self::FAILURE;
        }
        $agencyMap = [];
        foreach ($this->option('agency') as $mapping) {
            $parts = explode('=', $mapping, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || ! ctype_digit($parts[1])) {
                $this->error('Use --agency=CODE=CONTRACTOR_ID for each agency.');

                return self::FAILURE;
            }
            $agencyMap[trim($parts[0])] = (int) $parts[1];
        }
        try {
            $result = $importer->import($path, $agencyMap, $this->option('invalid-aadhaar'), $actor, (bool) $this->option('dry-run'));
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $prefix = $this->option('dry-run') ? 'Dry run' : 'Import complete';
        $this->info("{$prefix}: {$result['created']} new staff; {$result['skipped']} existing employee IDs skipped.");
        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
