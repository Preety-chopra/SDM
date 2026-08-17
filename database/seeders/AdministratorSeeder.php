<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class AdministratorSeeder extends Seeder
{
    public function run(): void
    {
        $credentials = Validator::make(config('administrator'), [
            'name' => ['required', 'string', 'max:255', new Username],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', Password::defaults()],
        ])->validate();

        $administrator = User::query()->updateOrCreate(
            ['email' => $credentials['email']],
            [
                'name' => $credentials['name'],
                'password' => $credentials['password'],
                'email_verified_at' => now(),
                'is_enabled' => true,
                'employee_number' => null,
                'phone' => null,
                'designation' => null,
                'station_id' => null,
            ],
        );

        $administrator->syncRoles([UserRole::Administrator->value]);
    }
}
