<?php

namespace Tests\Feature\Auth;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Line;
use App\Models\MemoWorkflowStep;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\TestWith;
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

    public function test_login_screen_offers_username_or_employee_number(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Username or Employee Number')
            ->assertSee('placeholder="Enter your username or employee number"', false);
    }

    #[TestWith(['name', 'sm02'])]
    #[TestWith(['employee_number', '001234'])]
    #[TestWith(['employee_number', 'EMP-001'])]
    #[TestWith(['employee_number', 'EMP/001_A'])]
    public function test_users_can_authenticate_with_username_or_employee_number(string $field, string $identifier): void
    {
        $user = User::factory()->create([$field => $identifier]);
        $user->assignRole(UserRole::Administrator->value);

        $this->post(route('login'), [
            'username' => $identifier,
            'password' => 'password',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.login',
        ]);
    }

    public function test_employee_number_login_requires_the_correct_password_and_audits_failure(): void
    {
        $user = User::factory()->create(['employee_number' => 'EMP/001_A']);

        $this->post(route('login'), [
            'username' => $user->employee_number,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors(['username' => trans('auth.failed')]);

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $user->id,
            'auditable_type' => User::class,
            'event' => 'auth.failed',
        ]);
    }

    public function test_disabled_users_cannot_login_with_employee_number(): void
    {
        $user = User::factory()->disabled()->create(['employee_number' => 'EMP-001']);

        $this->post(route('login'), [
            'username' => $user->employee_number,
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_unknown_employee_number_cannot_authenticate_another_user(): void
    {
        User::factory()->create(['employee_number' => 'EMP-001']);

        $this->post(route('login'), [
            'username' => 'EMP-999',
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => trans('auth.failed')]);

        $this->assertGuest();
    }

    public function test_station_manager_employee_number_login_still_requires_a_station(): void
    {
        $user = User::factory()->create(['employee_number' => 'SM-001']);
        $user->assignRole(UserRole::StationManager->value);

        $this->post(route('login'), [
            'username' => $user->employee_number,
            'password' => 'password',
        ])->assertSessionHasErrors([
            'station_id' => 'Select a station to sign in as a Station Controller or Station Manager.',
        ]);

        $this->assertGuest();
    }

    public function test_employee_number_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $this->freezeTime();
        $user = User::factory()->create(['employee_number' => 'EMP/001_A']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login'), [
                'username' => $user->employee_number,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors(['username' => trans('auth.failed')]);
        }

        $this->post(route('login'), [
            'username' => $user->employee_number,
            'password' => 'password',
        ])->assertSessionHasErrors(['username' => trans('auth.throttle', ['seconds' => 60, 'minutes' => 1])]);

        $this->assertGuest();
    }

    public function test_existing_username_takes_priority_over_another_users_employee_number(): void
    {
        User::factory()->create(['employee_number' => '001234', 'password' => 'other-password']);
        $user = User::factory()->create(['name' => '001234']);
        $user->assignRole(UserRole::Administrator->value);

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
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
            ->assertSee('Digitized Service Deficiency Memo')
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

    #[TestWith(['station', '/'])]
    #[TestWith(['station', '/login'])]
    #[TestWith(['station_controller', '/'])]
    #[TestWith(['station_controller', '/login'])]
    public function test_authenticated_station_controller_visiting_a_login_entry_page_is_redirected_to_create_memo(string $role, string $path): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));

        $this->actingAs($user)->get($path)
            ->assertRedirect(route('sdm-memos.create'));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'activity.request',
            'after_values->status' => 302,
        ]);
    }

    public function test_authenticated_administrator_visiting_the_homepage_is_redirected_to_the_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);

        $this->actingAs($user)->get('/')
            ->assertRedirect(route('dashboard'));
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

    public function test_the_same_user_can_keep_multiple_browser_sessions_active(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $credentials = ['username' => $user->name, 'password' => 'password', 'remember' => 'on'];
        $sessionCookie = config('session.cookie');

        $firstLogin = $this->post(route('login'), $credentials)->assertRedirect(route('dashboard', absolute: false));
        $firstSession = $firstLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $secondLogin = $this->post(route('login'), $credentials)->assertRedirect(route('dashboard', absolute: false));
        $secondSession = $secondLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $this->call('GET', route('dashboard'), cookies: [$sessionCookie => $firstSession])
            ->assertOk();

        $this->resetBrowserRequest();
        $this->call('GET', route('dashboard'), cookies: [$sessionCookie => $secondSession])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'auth.other_devices_revoked']);
    }

    public function test_failed_login_on_another_device_keeps_the_current_session(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $sessionCookie = config('session.cookie');
        $login = $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));
        $session = $login->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'invalid-password',
        ])->assertSessionHasErrors('username');

        $this->resetBrowserRequest();
        $this->call('GET', route('dashboard'), cookies: [$sessionCookie => $session])->assertOk();
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

    public function test_line_manager_sees_only_counts_of_their_current_pending_sdms_after_login(): void
    {
        $manager = User::factory()->create(['employee_number' => 'AM-LOGIN']);
        $manager->assignRole(UserRole::AssistantManager->value);
        $otherManager = User::factory()->create(['employee_number' => 'AM-OTHER']);
        $otherManager->assignRole(UserRole::AssistantManager->value);
        $line = Line::query()->create(['code' => 'LOGIN-LINE', 'name' => 'Login Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'LOGIN-STATION',
            'name' => 'Login Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);

        $olderPending = $this->lineReviewMemo($manager, $station, 'AM-PENDING-OLDER', '2026-10-01 09:00:00');
        $newerPending = $this->lineReviewMemo($manager, $station, 'AM-PENDING-NEWER', '2026-10-04 09:00:00');
        $newerPending->update(['is_urgent' => true]);
        $forwarded = $this->lineReviewMemo($manager, $station, 'AM-FORWARDED', '2026-10-05 09:00:00', 'forwarded');
        $otherManagerPending = $this->lineReviewMemo($otherManager, $station, 'OTHER-AM-PENDING', '2026-10-05 10:00:00');

        $response = $this->post(route('login'), [
            'username' => $manager->name,
            'password' => 'password',
        ]);

        $response
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('pending_sdm_summary', ['total' => 2, 'urgent' => 1]);

        $dashboard = $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Pending SDMs awaiting your action')
            ->assertSee('Pending SDMs')
            ->assertSee('Urgent SDMs')
            ->assertDontSee($forwarded->serial_number)
            ->assertDontSee($otherManagerPending->serial_number);

        preg_match('/<section[^>]*aria-labelledby="pending-sdm-title".*?<\/section>/s', $dashboard->getContent(), $modal);
        $this->assertNotEmpty($modal);
        $this->assertMatchesRegularExpression('/Pending SDMs<\/dt>\s*<dd[^>]*>2<\/dd>/', $modal[0]);
        $this->assertMatchesRegularExpression('/Urgent SDMs<\/dt>\s*<dd[^>]*>1<\/dd>/', $modal[0]);
        $this->assertStringNotContainsString($olderPending->serial_number, $modal[0]);
        $this->assertStringNotContainsString($newerPending->serial_number, $modal[0]);
        $this->assertStringNotContainsString(route('sdm-memos.show', $newerPending), $modal[0]);
        $this->assertStringContainsString('href="'.route('sdm-memos.index', ['category' => 'pending_action']).'"', $modal[0]);
        $this->assertStringContainsString('href="'.route('sdm-memos.index', ['category' => 'urgent_pending_action']).'"', $modal[0]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Pending SDMs awaiting your action');
    }

    #[TestWith(['station_manager_signoff'])]
    #[TestWith(['line_penalty_review'])]
    #[TestWith(['am_cmc_to_scrutiny'])]
    #[TestWith(['cmc_assignment'])]
    #[TestWith(['cmc_assessment'])]
    #[TestWith(['cmc_verification'])]
    #[TestWith(['am_cmc_to_dgm'])]
    #[TestWith(['dgm_cmc_approval'])]
    public function test_login_counts_only_sdms_currently_pending_with_the_user_at_each_workflow_level(string $stageValue): void
    {
        $stage = WorkflowStepType::from($stageValue);
        $user = User::factory()->create();
        $user->assignRole($stage->role()->value);
        $otherUser = User::factory()->create();
        $station = Station::query()->create(['code' => 'QUEUE', 'name' => 'Queue Station', 'is_active' => true]);

        $this->workflowMemo($user, $station, $stage, 'MY-NORMAL');
        $this->workflowMemo($user, $station, $stage, 'MY-URGENT', true);
        $this->workflowMemo($otherUser, $station, $stage, 'OTHER-URGENT', true);
        $this->workflowMemo($user, $station, $stage, 'ALREADY-ACTED', true, 'forwarded');
        $movedMemo = $this->workflowMemo($user, $station, $stage, 'MOVED-TO-NEXT-USER', true);
        $nextStep = MemoWorkflowStep::query()->create([
            'memo_id' => $movedMemo->id,
            'round' => 1,
            'sequence' => 2,
            'stage' => WorkflowStepType::DgmApproval->value,
            'role' => UserRole::DeputyGeneralManager->value,
            'assigned_to' => $otherUser->id,
            'assigned_name' => $otherUser->name,
            'decision' => 'pending',
        ]);
        $movedMemo->update(['current_workflow_step_id' => $nextStep->id]);

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('pending_sdm_summary', ['total' => 2, 'urgent' => 1]);
    }

    #[TestWith(['administrator'])]
    #[TestWith(['station'])]
    #[TestWith(['station_controller'])]
    public function test_login_does_not_open_the_pending_queue_popup_for_administrators_or_station_controllers(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role));
        $station = Station::query()->create(['code' => 'NO-POPUP', 'name' => 'No Popup Station', 'is_active' => true]);

        $this->withSession(['url.intended' => route('sdm-memos.index')])->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])
            ->assertRedirect(route('sdm-memos.index'))
            ->assertSessionMissing('pending_sdm_summary');

        $this->assertAuthenticatedAs($user);

        $this->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertDontSee('Pending SDMs awaiting your action');
    }

    #[TestWith(['station_manager'])]
    #[TestWith(['assistant_manager'])]
    public function test_login_displays_zero_counts_when_the_user_has_no_pending_sdms(string $role): void
    {
        $user = User::factory()->create();
        $user->assignRole($role);
        $station = Station::query()->create(['code' => 'EMPTY', 'name' => 'Empty Station', 'is_active' => true]);

        $response = $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])
            ->assertRedirect()
            ->assertSessionHas('pending_sdm_summary', ['total' => 0, 'urgent' => 0]);

        $this->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee('Pending SDMs awaiting your action')
            ->assertSee('Pending SDMs')
            ->assertSee('Urgent SDMs');
    }

    public function test_login_counts_all_urgent_sdms_without_the_old_ten_memo_limit(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::CmcStaff->value);
        $station = Station::query()->create(['code' => 'URGENT', 'name' => 'Urgent Station', 'is_active' => true]);
        for ($index = 1; $index <= 12; $index++) {
            $this->workflowMemo($user, $station, WorkflowStepType::CmcAssessment, 'URGENT-'.$index, true);
        }

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false))
            ->assertSessionHas('pending_sdm_summary', ['total' => 12, 'urgent' => 12]);
    }

    #[TestWith(['pending_action', ['MY-NORMAL', 'MY-URGENT', 'TRANSFERRED-URGENT']])]
    #[TestWith(['urgent_pending_action', ['MY-URGENT', 'TRANSFERRED-URGENT']])]
    public function test_pending_summary_card_lists_only_the_matching_current_assignments(string $category, array $expectedSerials): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::StationManager->value);
        $station = Station::query()->create(['code' => 'CARDS', 'name' => 'Cards Station', 'is_active' => true]);
        $this->workflowMemo($user, $station, WorkflowStepType::StationManager, 'MY-NORMAL');
        $this->workflowMemo($user, $station, WorkflowStepType::StationManager, 'MY-URGENT', true);
        $transferred = $this->workflowMemo($user, $station, WorkflowStepType::StationManager, 'TRANSFERRED-URGENT', true);
        $transferred->currentWorkflowStep()->update(['assignment_source' => 'handover']);
        $this->workflowMemo(User::factory()->create(), $station, WorkflowStepType::StationManager, 'OTHER-URGENT', true);
        $this->workflowMemo($user, $station, WorkflowStepType::StationManager, 'ALREADY-ACTED', true, 'forwarded');

        $response = $this->actingAs($user)->get(route('sdm-memos.index', ['category' => $category]))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('serial_number')->sort()->values()->all() === $expectedSerials)
            ->assertViewHas('memoCategoryTabs', fn (array $tabs): bool => array_column($tabs, 'label') === ['All Memos', 'Pending Memos', 'Transferred Memos'])
            ->assertSee('name="category" value="'.$category.'"', false);

        preg_match('/<nav aria-label="SDM categories".*?<\/nav>/s', $response->getContent(), $navigation);
        $this->assertNotEmpty($navigation);
        $this->assertMatchesRegularExpression('/<a\s+href="'.preg_quote(route('sdm-memos.index', ['category' => 'pending_action']), '/').'"\s+aria-current="page"[^>]*>Pending Memos<\/a>/s', $navigation[0]);
    }

    #[TestWith(['pending_action'])]
    #[TestWith(['urgent_pending_action'])]
    public function test_station_users_open_an_empty_pending_list_instead_of_their_submitted_memos(string $category): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Station->value);
        $station = Station::query()->create(['code' => 'EMPTY-CARD', 'name' => 'Empty Queue', 'is_active' => true]);
        $this->workflowMemo($user, $station, WorkflowStepType::StationManager, 'SUBMITTED-ELSEWHERE', true, 'forwarded');

        $this->actingAs($user)->get(route('sdm-memos.index', ['category' => $category]))
            ->assertOk()
            ->assertViewIs('sdm-memos.index')
            ->assertViewHas('memos', fn ($memos): bool => $memos->total() === 0);
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

    public function test_logout_ends_only_the_current_browser_session(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $sessionCookie = config('session.cookie');
        $credentials = [
            'username' => $user->name,
            'password' => 'password',
        ];
        $firstLogin = $this->post(route('login'), $credentials)->assertRedirect(route('dashboard', absolute: false));
        $firstSession = $firstLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $secondLogin = $this->post(route('login'), $credentials)->assertRedirect(route('dashboard', absolute: false));
        $secondSession = $secondLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $this->call('POST', route('logout'), cookies: [$sessionCookie => $secondSession])
            ->assertRedirect('/');

        $this->resetBrowserRequest();
        $this->call('GET', route('dashboard'), cookies: [$sessionCookie => $secondSession])
            ->assertRedirect(route('login'));

        $this->resetBrowserRequest();
        $this->call('GET', route('dashboard'), cookies: [$sessionCookie => $firstSession])->assertOk();
    }

    #[TestWith([UserRole::Administrator])]
    #[TestWith([UserRole::Station])]
    #[TestWith([UserRole::StationManager])]
    #[TestWith([UserRole::AssistantManager])]
    #[TestWith([UserRole::ManagerOperations])]
    #[TestWith([UserRole::CmcTeamLead])]
    #[TestWith([UserRole::CmcStaff])]
    #[TestWith([UserRole::DeputyGeneralManager])]
    #[TestWith([UserRole::Aft])]
    #[TestWith([UserRole::DgmAft])]
    public function test_every_role_is_logged_out_after_fifteen_minutes_of_inactivity(UserRole $role): void
    {
        $this->freezeTime();
        // Retained sessions must also obey the limit when a deployment still has an older lifetime setting.
        config(['session.lifetime' => 120]);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $station = Station::query()->create(['code' => 'IDLE', 'name' => 'Idle Station', 'is_active' => true]);
        $sessionCookie = config('session.cookie');
        $login = $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])->assertRedirect();
        $session = $login->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->travel(15)->minutes();
        $this->resetBrowserRequest();
        $this->call('GET', route('profile.edit'), cookies: [$sessionCookie => $session])
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.logout',
            'after_values->reason' => 'inactivity',
        ]);
    }

    public function test_activity_extends_only_its_own_browser_session(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $sessionCookie = config('session.cookie');
        $credentials = ['username' => $user->name, 'password' => 'password'];
        $firstLogin = $this->post(route('login'), $credentials)->assertRedirect();
        $firstSession = $firstLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->resetBrowserRequest();
        $secondLogin = $this->post(route('login'), $credentials)->assertRedirect();
        $secondSession = $secondLogin->getCookie($sessionCookie, decrypt: false)->getValue();

        $this->travel(14)->minutes();
        $this->resetBrowserRequest();
        $this->call('GET', route('profile.edit'), cookies: [$sessionCookie => $firstSession])->assertOk();

        $this->travel(1)->minutes();
        $this->resetBrowserRequest();
        $this->call('GET', route('profile.edit'), cookies: [$sessionCookie => $secondSession])
            ->assertRedirect(route('login'));

        $this->resetBrowserRequest();
        $this->call('GET', route('profile.edit'), cookies: [$sessionCookie => $firstSession])->assertOk();
    }

    public function test_remember_me_cannot_bypass_the_inactivity_timeout(): void
    {
        $user = User::factory()->create();
        $rememberCookie = Auth::guard('web')->getRecallerName();

        $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
            'remember' => 'on',
        ])->assertRedirect()->assertCookieMissing($rememberCookie);
    }

    public function test_a_legacy_remember_cookie_requires_a_fresh_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $guard = Auth::guard('web');
        $guard->login($user, true);
        $rememberCookie = $guard->getRecallerName();
        $remember = app('cookie')->queued($rememberCookie)->getValue();

        $this->resetBrowserRequest();
        $this->withCookie($rememberCookie, $remember)->get(route('profile.edit'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_a_session_created_before_inactivity_tracking_requires_a_fresh_login(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);
        $sessionCookie = config('session.cookie');
        $login = $this->post(route('login'), [
            'username' => $user->name,
            'password' => 'password',
        ])->assertRedirect();
        $session = $login->getCookie($sessionCookie, decrypt: false)->getValue();
        $this->app['session.store']->forget('auth.last_activity_at');
        $this->app['session.store']->save();

        $this->resetBrowserRequest();
        $this->call('GET', route('profile.edit'), cookies: [$sessionCookie => $session])
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function resetBrowserRequest(): void
    {
        Auth::forgetGuards();
        $this->flushSession();
    }

    private function workflowMemo(User $user, Station $station, WorkflowStepType $stage, string $serialNumber, bool $urgent = false, string $decision = 'pending'): ServiceDeficiencyMemo
    {
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serialNumber,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => MemoStatus::UnderReview->value,
            'workflow_version' => 2,
            'created_by' => $user->id,
            'submitted_at' => now(),
            'is_urgent' => $urgent,
        ]);
        $step = MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => 1,
            'stage' => $stage->value,
            'role' => $stage->role()->value,
            'assigned_to' => $user->id,
            'assigned_name' => $user->name,
            'decision' => $decision,
        ]);
        $memo->update(['current_workflow_step_id' => $step->id]);

        return $memo;
    }

    private function lineReviewMemo(
        User $manager,
        Station $station,
        string $serialNumber,
        string $submittedAt,
        string $decision = 'pending',
    ): ServiceDeficiencyMemo {
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => $serialNumber,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'line_code' => $station->metroLine?->code,
            'line_name' => $station->metroLine?->name,
            'status' => $decision === 'pending' ? MemoStatus::LineReview->value : MemoStatus::AmCmcReview->value,
            'workflow_version' => 2,
            'created_by' => $manager->id,
            'submitted_at' => $submittedAt,
        ]);
        $step = MemoWorkflowStep::query()->create([
            'memo_id' => $memo->id,
            'round' => 1,
            'sequence' => 2,
            'stage' => WorkflowStepType::LineReview->value,
            'role' => UserRole::AssistantManager->value,
            'assigned_to' => $manager->id,
            'assigned_name' => $manager->name,
            'assigned_employee_number' => $manager->employee_number,
            'decision' => $decision,
        ]);

        if ($decision === 'pending') {
            $memo->update(['current_workflow_step_id' => $step->id]);
        }

        return $memo;
    }
}
