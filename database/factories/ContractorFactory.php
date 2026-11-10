<?php

namespace Database\Factories;

use App\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contractor> */
class ContractorFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->unique()->company(), 'is_active' => true];
    }
}
