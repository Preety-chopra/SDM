<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_only_administrators_can_access_user_management(): void
    {
        $managedUser = $this->userWithRole(UserRole::Station);

        $this->get(route('users.index', UserRole::Station->value))
            ->assertRedirect(route('login'));

        $this->actingAs($managedUser)
            ->get(route('users.index', UserRole::Station->value))
            ->assertForbidden();

        $this->actingAs($managedUser)
            ->post(route('users.store'), $this->validUserData(UserRole::Station))
            ->assertForbidden();

        $this->actingAs($managedUser)
            ->get(route('users.edit', $managedUser))
            ->assertForbidden();

        $this->actingAs($managedUser)
            ->patch(route('users.status', $managedUser), ['is_enabled' => false])
            ->assertForbidden();
    }

    public function test_administrator_dashboard_displays_managed_role_counts(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $this->userWithRole(UserRole::Station);
        $this->userWithRole(UserRole::Station, ['is_enabled' => false]);
        $this->userWithRole(UserRole::AssistantManager);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Station Users')
            ->assertSee('Assistant Managers')
            ->assertSee('Deputy General Managers')
            ->assertSee('Enabled')
            ->assertSee('Disabled');
    }

    public function test_non_administrator_receives_the_basic_dashboard_without_user_management(): void
    {
        $user = $this->userWithRole(UserRole::Station);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('You are logged in')
            ->assertDontSee('User management')
            ->assertDontSee('Users menu');
    }

    public function test_administrator_can_create_a_station_manager(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $station = Station::query()->create([
            'code' => 'ST-01',
            'name' => 'Central Station',
            'is_active' => true,
        ]);

        $response = $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::StationManager),
            'employee_number' => 'EMP-1001',
            'station_id' => $station->id,
        ]);

        $createdUser = User::query()->where('email', 'managed@example.com')->sole();

        $response->assertRedirect(route('users.index', UserRole::StationManager->value));
        $this->assertTrue($createdUser->hasExactRoles(UserRole::StationManager->value));
        $this->assertSame($station->id, $createdUser->station_id);
        $this->assertTrue(Hash::check('Secure Password 123!', $createdUser->password));
    }

    public function test_role_specific_personnel_and_station_rules_are_enforced(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validUserData(UserRole::StationManager))
            ->assertSessionHasErrors(['employee_number', 'station_id']);

        $this->actingAs($administrator)
            ->post(route('users.store'), [
                ...$this->validUserData(UserRole::AssistantManager),
                'employee_number' => 'EMP-1002',
                'station_id' => 999,
            ])
            ->assertSessionHasErrors(['station_id']);

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validUserData(UserRole::Administrator))
            ->assertSessionHasErrors(['role']);
    }

    public function test_administrator_can_change_role_and_leave_password_unchanged(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $station = Station::query()->create([
            'code' => 'ST-02',
            'name' => 'North Station',
            'is_active' => true,
        ]);
        $managedUser = $this->userWithRole(UserRole::StationManager, [
            'employee_number' => 'EMP-2001',
            'station_id' => $station->id,
        ]);
        $password = $managedUser->password;

        $response = $this->actingAs($administrator)->put(route('users.update', $managedUser), [
            'name' => 'Updated Manager',
            'email' => $managedUser->email,
            'role' => UserRole::AssistantManager->value,
            'employee_number' => 'EMP-2001',
            'phone' => '9876543210',
            'designation' => 'Assistant Manager',
            'is_enabled' => '1',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $managedUser->refresh();

        $response->assertRedirect(route('users.index', UserRole::AssistantManager->value));
        $this->assertTrue($managedUser->hasExactRoles(UserRole::AssistantManager->value));
        $this->assertNull($managedUser->station_id);
        $this->assertSame($password, $managedUser->password);
    }

    public function test_administrator_can_reset_a_managed_users_password(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $managedUser = $this->userWithRole(UserRole::AssistantManager, [
            'employee_number' => 'EMP-2101',
        ]);

        $this->actingAs($administrator)->put(route('users.update', $managedUser), [
            'name' => $managedUser->name,
            'email' => $managedUser->email,
            'role' => UserRole::AssistantManager->value,
            'employee_number' => $managedUser->employee_number,
            'phone' => null,
            'designation' => null,
            'station_id' => null,
            'is_enabled' => '1',
            'password' => 'Replacement Password 456!',
            'password_confirmation' => 'Replacement Password 456!',
        ])->assertRedirect();

        $this->assertTrue(Hash::check('Replacement Password 456!', $managedUser->fresh()->password));
    }

    public function test_email_and_employee_number_must_be_unique(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $existingUser = $this->userWithRole(UserRole::AssistantManager, [
            'email' => 'existing@example.com',
            'employee_number' => 'EMP-2201',
        ]);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::DeputyGeneralManager),
            'email' => $existingUser->email,
            'employee_number' => $existingUser->employee_number,
        ])->assertSessionHasErrors(['email', 'employee_number']);
    }

    public function test_username_must_be_a_unique_name(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $existingUser = $this->userWithRole(UserRole::Station, [
            'name' => 'Central Station',
        ]);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::Station),
            'name' => $existingUser->name,
        ])->assertSessionHasErrors('name');

        foreach (['station_12', 'person@example.com', 'Administrator 1'] as $invalidName) {
            $this->actingAs($administrator)->post(route('users.store'), [
                ...$this->validUserData(UserRole::Station),
                'name' => $invalidName,
            ])->assertSessionHasErrors('name');
        }
    }

    public function test_invalid_and_administrator_role_directories_are_not_available(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)->get('/users/not-a-role')->assertNotFound();
        $this->actingAs($administrator)->get('/users/'.UserRole::Administrator->value)->assertNotFound();
    }

    public function test_administrator_can_filter_role_directory_and_change_status(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $match = $this->userWithRole(UserRole::Station, [
            'name' => 'Searchable Station',
            'email' => 'searchable@example.com',
        ]);
        $this->userWithRole(UserRole::Station, [
            'name' => 'Hidden Station',
            'is_enabled' => false,
        ]);
        $this->userWithRole(UserRole::AssistantManager, [
            'name' => 'Wrong Role',
            'employee_number' => 'EMP-3001',
        ]);

        $this->actingAs($administrator)
            ->get(route('users.index', [
                'role' => UserRole::Station->value,
                'search' => 'searchable',
                'status' => 'enabled',
            ]))
            ->assertOk()
            ->assertSee('Searchable Station')
            ->assertDontSee('Hidden Station')
            ->assertDontSee('Wrong Role');

        $this->actingAs($administrator)
            ->patch(route('users.status', $match), ['is_enabled' => false])
            ->assertRedirect();

        $this->assertFalse($match->fresh()->is_enabled);
    }

    public function test_administrator_accounts_cannot_be_managed(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $otherAdministrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get(route('users.edit', $otherAdministrator))
            ->assertForbidden();

        $this->actingAs($administrator)
            ->patch(route('users.status', $otherAdministrator), ['is_enabled' => false])
            ->assertForbidden();
    }

    private function userWithRole(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }

    private function validUserData(UserRole $role): array
    {
        return [
            'name' => 'Managed User',
            'email' => 'managed@example.com',
            'role' => $role->value,
            'employee_number' => null,
            'phone' => null,
            'designation' => null,
            'station_id' => null,
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ];
    }
}
