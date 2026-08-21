<?php

namespace Tests\Feature;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_authorized_users_can_manage_roles_and_permissions(): void
    {
        $stationUser = $this->userWithRole(UserRole::Station);

        $this->get(route('roles.index'))->assertRedirect(route('login'));
        $this->actingAs($stationUser)->get(route('roles.index'))->assertForbidden();
        $this->actingAs($stationUser)->post(route('roles.store'), [
            'name' => 'auditor',
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_administrator_can_create_a_role_with_existing_and_new_permissions(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)->post(route('roles.store'), [
            'name' => 'quality_auditor',
            'permissions' => [UserPermission::ViewAllMemos->value],
            'new_permissions' => "reports.view\nreports.export",
        ])->assertRedirect(route('roles.index'));

        $role = Role::findByName('quality_auditor');

        $this->assertTrue($role->hasPermissionTo(UserPermission::ViewAllMemos->value));
        $this->assertTrue($role->hasPermissionTo('reports.view'));
        $this->assertTrue($role->hasPermissionTo('reports.export'));
        $this->assertDatabaseHas('permissions', ['name' => 'reports.view', 'guard_name' => 'web']);
    }

    public function test_administrator_can_update_permissions_on_a_custom_role(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $role = Role::findOrCreate('auditor');
        $role->givePermissionTo(UserPermission::ViewAllMemos->value);

        $this->actingAs($administrator)->put(route('roles.update', $role), [
            'name' => 'senior_auditor',
            'permissions' => [UserPermission::CreateMemos->value],
            'new_permissions' => 'audits.approve',
        ])->assertRedirect(route('roles.index'));

        $role->refresh();

        $this->assertSame('senior_auditor', $role->name);
        $this->assertEqualsCanonicalizing([
            UserPermission::CreateMemos->value,
            'audits.approve',
        ], $role->permissions->pluck('name')->all());
    }

    public function test_administrator_role_cannot_be_modified(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $administratorRole = Role::findByName(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get(route('roles.edit', $administratorRole))
            ->assertForbidden();

        $this->actingAs($administrator)->put(route('roles.update', $administratorRole), [
            'name' => 'renamed_administrator',
            'permissions' => [],
        ])->assertForbidden();
    }

    public function test_custom_role_is_available_when_creating_and_browsing_users(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $role = Role::findOrCreate('quality_auditor');

        $this->actingAs($administrator)
            ->get(route('users.create', $role->name))
            ->assertOk()
            ->assertSee('Quality Auditor');

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Quality User',
            'email' => 'quality@example.com',
            'role' => $role->name,
            'employee_number' => null,
            'phone' => null,
            'designation' => 'Auditor',
            'station_id' => null,
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ])->assertRedirect(route('users.index', $role->name));

        $createdUser = User::query()->where('email', 'quality@example.com')->sole();
        $this->assertTrue($createdUser->hasExactRoles($role->name));

        $this->actingAs($administrator)
            ->get(route('users.index', $role->name))
            ->assertOk()
            ->assertSee('Quality User');
    }

    public function test_role_and_permission_names_are_validated(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)->post(route('roles.store'), [
            'name' => 'Invalid Role!',
            'permissions' => ['missing.permission'],
            'new_permissions' => 'Invalid Permission!',
        ])->assertSessionHasErrors(['name', 'permissions.0', 'new_permissions.0']);

        $this->assertFalse(Permission::query()->where('name', 'Invalid Permission!')->exists());
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
