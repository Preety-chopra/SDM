<?php

namespace Database\Seeders;

use App\Services\HistoricalSdmSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HistoricalSdmSeeder extends Seeder
{
    public function run(HistoricalSdmSeedService $history): void
    {
        $disk = Storage::disk('local');
        $path = 'seed-data/historical-sdms.json';
        if (! $disk->exists($path)) {
            throw new RuntimeException("Upload the private historical manifest to storage/app/private/{$path} and its XLSX workbooks to the same directory before running HistoricalSdmSeeder.");
        }

        $manifest = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $result = $history->seed($manifest);

        $this->command?->info("Historical SDMs: {$result['created']} created; {$result['skipped']} previously imported rows skipped.");
        foreach ($result['issues'] as $issue => $count) {
            $this->command?->warn("{$issue}: {$count} rows (source values retained).");
        }
    }
}
