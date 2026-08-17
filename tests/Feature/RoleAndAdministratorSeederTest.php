<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdministratorSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndAdministratorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_permissions_and_administrator_are_seeded_idempotently(): void
    {
        config()->set('administrator', [
            'name' => 'System Administrator',
            'email' => 'administrator@example.com',
            'password' => 'Secure Password 123!',
        ]);

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AdministratorSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AdministratorSeeder::class);

        $this->assertSame(count(UserRole::cases()), Role::query()->count());
        $this->assertSame(count(UserPermission::cases()), Permission::query()->count());

        $administrator = User::query()->where('email', 'administrator@example.com')->sole();

        $this->assertTrue($administrator->is_enabled);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('Secure Password 123!', $administrator->password));
        $this->assertTrue($administrator->hasExactRoles(UserRole::Administrator->value));

        foreach (UserPermission::cases() as $permission) {
            $this->assertTrue($administrator->can($permission->value));
        }
    }

    public function test_administrator_seeder_requires_environment_credentials(): void
    {
        config()->set('administrator', [
            'name' => null,
            'email' => null,
            'password' => null,
        ]);

        $this->expectException(ValidationException::class);

        $this->seed(AdministratorSeeder::class);
    }
}
