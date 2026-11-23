<?php

namespace Database\Seeders;

use App\Services\OutsourcedStaffSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OutsourcedStaffSeeder extends Seeder
{
    public function run(OutsourcedStaffSeedService $staff): void
    {
        $disk = Storage::disk('local');
        $path = 'seed-data/outsourced-staff.json';
        if (! $disk->exists($path)) {
            throw new RuntimeException("Upload the private staff snapshot to storage/app/private/{$path} before running OutsourcedStaffSeeder.");
        }

        $snapshot = json_decode($disk->get($path), true, flags: JSON_THROW_ON_ERROR);
        $result = $staff->seed($snapshot);

        $this->command?->info("Outsourced staff: {$result['created']} created; {$result['skipped']} existing employee IDs skipped.");
    }
}
