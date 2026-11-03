<?php

namespace Database\Seeders;

use App\Services\LocationSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class StationSeeder extends Seeder
{
    public function run(LocationSeedService $locations): void
    {
        $snapshot = json_decode(
            File::get(database_path('seeders/data/locations.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $locations->seedStations($snapshot);
    }
}
