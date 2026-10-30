<?php

namespace App\Console\Commands;

use App\Services\StationMasterImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('stations:import-master')]
#[Description('Merge station_master reference rows into stations, preserving existing station IDs')]
class ImportStationMaster extends Command
{
    public function handle(StationMasterImportService $importer): int
    {
        try {
            $counts = $importer->import();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Station master imported: {$counts['created']} created, {$counts['updated']} updated, {$counts['unchanged']} unchanged.");

        return self::SUCCESS;
    }
}
