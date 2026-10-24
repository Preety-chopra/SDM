<?php

namespace Database\Seeders;

use App\Models\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['code' => 'SM', 'name' => 'Station Manager'],
            ['code' => 'Sr. SM', 'name' => 'Senior Station Manager'],
            ['code' => 'AM', 'name' => 'Assistant Manager'],
            ['code' => 'MGR', 'name' => 'Manager'],
            ['code' => 'DGM', 'name' => 'Deputy General Manager'],
        ] as $designation) {
            Designation::query()->updateOrCreate(
                ['code' => $designation['code']],
                [...$designation, 'is_active' => true],
            );
        }
    }
}
