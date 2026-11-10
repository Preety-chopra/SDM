<?php

namespace Database\Factories;

use App\Models\Contractor;
use App\Models\OutsourcedStaff;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<OutsourcedStaff> */
class OutsourcedStaffFactory extends Factory
{
    public function definition(): array
    {
        return [
            'contractor_id' => Contractor::factory(),
            'employee_id' => fake()->unique()->numerify('9#####'),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'date_of_birth' => '1990-08-25',
            'designation' => 'TOM Operator',
            'verification_status' => 'YES',
            'is_active' => true,
        ];
    }
}
