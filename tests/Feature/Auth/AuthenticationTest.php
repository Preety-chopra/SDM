<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_forgot_password_link_displays_it_cell_contact_popup(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Forgot password?')
            ->assertSee('Please contact Ops IT Cell - 110426 or send an email to itcellops@dmrc.org')
            ->assertSee('@click="forgotPasswordOpen = true"', false)
            ->assertSee('role="dialog"', false);
    }

    public function test_homepage_displays_the_login_screen(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('Service Deficiency Management')
            ->assertSee('A secure workspace for creating, reviewing, and tracking service deficiency memos across DMRC operations.')
            ->assertSee('src="'.asset('images/dmrc-logo.svg').'"', false)
            ->assertSee('alt="Delhi Metro Rail Corporation"', false)
            ->assertSee('Welcome back')
            ->assertSee('Sign in to your account')
            ->assertDontSee('Every decision starts with a clearer view')
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertDontSee('name="line_id"', false)
            ->assertSee('action="'.route('login').'"', false);
    }

    public function test_station_controller_can_authenticate_using_the_login_screen_with_a_station(): void
    {
        $user = User::factory()->create(['name' => 'Central Station']);
        $user->assignRole(UserRole::Station->value);
        $station = Station::query()->create([
            'code' => 'CENTRAL',
            'name' => 'Central Station',
            'is_active' => true,
        ]);

        $response = $this->post('/login', [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ]);

        $this->assertAuthenticated();
        $response
            ->assertRedirect(route('sdm-memos.create', absolute: false))
            ->assertSessionHas('auth.station_id', $station->id);
    }

    public function test_legacy_station_controller_role_is_redirected_to_the_create_memo_page_after_login(): void
    {
        $role = Role::findOrCreate('station_controller');
        $user = User::factory()->create();
        $user->assignRole($role);
        $station = Station::query()->create([
            'code' => 'LEGACY',
            'name' => 'Legacy Station',
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])->assertRedirect(route('sdm-memos.create', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_station_manager_still_redirects_to_the_dashboard_after_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::StationManager->value);
        $station = Station::query()->create([
            'code' => 'SM-STATION',
            'name' => 'SM Station',
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_lists_only_active_stations(): void
    {
        $line = Line::query()->create(['code' => 'BLUE', 'name' => 'Blue Line', 'is_active' => true]);
        $zulu = Station::query()->create(['code' => 'ZULU', 'name' => 'Zulu Station', 'line_id' => $line->id, 'is_active' => true]);
        $alpha = Station::query()->create(['code' => 'ALPHA', 'name' => 'Alpha Station', 'line_id' => $line->id, 'is_active' => true]);
        $disabled = Station::query()->create(['code' => 'DISABLED', 'name' => 'Disabled Station', 'line_id' => $line->id, 'is_active' => false]);
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Station', 'Zulu Station'])
            ->assertDontSee('Disabled Station')
            ->assertSee('name="station_id"', false)
            ->assertSee('id="station_id" name="station_id"', false)
            ->assertDontSee('name="station_id" required', false);
    }

    public function test_station_controller_and_station_manager_must_select_an_active_station(): void
    {
        $disabled = Station::query()->create([
            'code' => 'DISABLED',
            'name' => 'Disabled Station',
            'is_active' => false,
        ]);

        Role::findOrCreate('station_controller');

        foreach ([UserRole::Station->value, UserRole::StationManager->value, 'station_controller'] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role);

            $this->post(route('login'), [
                'username' => $user->name,
                'password' => 'password',
            ])->assertSessionHasErrors('station_id');
            $this->assertGuest();

            $this->post(route('login'), [
                'username' => $user->name,
                'password' => 'password',
                'station_id' => $disabled->id,
            ])->assertSessionHasErrors('station_id');
            $this->assertGuest();
        }
    }

    public function test_non_station_roles_can_login_without_selecting_a_station(): void
    {
        foreach ([
            UserRole::Administrator,
            UserRole::AssistantManager,
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);

            $this->post(route('login'), [
                'username' => $user->name,
                'password' => 'password',
            ])->assertRedirect(route('dashboard', absolute: false))
                ->assertSessionMissing('auth.station_id');
            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'));
        }
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->name,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_disabled_users_can_not_authenticate(): void
    {
        $user = User::factory()->disabled()->create();

        $this->post('/login', [
            'username' => $user->name,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_using_their_email_address(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'username' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_disabled_authenticated_users_are_logged_out_on_their_next_request(): void
    {
        $user = User::factory()->disabled()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
