<?php

namespace Database\Seeders;

use App\Services\ServiceContractSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class ServiceContractSeeder extends Seeder
{
    public function run(ServiceContractSeedService $contracts): void
    {
        $snapshot = json_decode(
            File::get(database_path('seeders/data/service-contracts.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $snapshot += json_decode(
            File::get(database_path('seeders/data/locations.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $contracts->seed($snapshot);
    }
}
