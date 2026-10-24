<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
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
            ->assertSee('Managers/Assistant Managers (O)-Line')
            ->assertSee('DGM/O Users')
            ->assertSee('Enabled')
            ->assertSee('Disabled');
    }

    public function test_administrator_dashboard_exposes_primary_tools_as_direct_links_without_laravel_branding(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('SDM Workspace')
            ->assertSee('Quick actions')
            ->assertSee(route('sdm-memos.index'), false)
            ->assertSee(route('users.create_any'), false)
            ->assertSee(route('lines.index'), false)
            ->assertSee(route('stations.index'), false)
            ->assertSee(route('contractors.index'), false)
            ->assertDontSee('viewBox="0 0 316 316"', false);
    }

    public function test_administrator_dashboard_presents_contract_workflow_as_a_scannable_stage_board(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('workflowStepTypes', fn (array $stepTypes): bool => count($stepTypes) === 8)
            ->assertSee('data-workflow-board', false)
            ->assertSee('data-workflow-stage="station_manager_signoff"', false)
            ->assertSee('aria-label="Stage 1 of 8"', false)
            ->assertSee('Queue clear');
    }

    public function test_administrator_dashboard_lists_pending_memos_at_each_approval_level(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $station = Station::query()->create([
            'code' => 'PENDING-01',
            'name' => 'Pending Station',
            'is_active' => true,
        ]);

        foreach (WorkflowStage::cases() as $stage) {
            $memo = ServiceDeficiencyMemo::query()->create([
                'service_type' => SdmType::Cfs->value,
                'serial_number' => 'PENDING-'.$stage->sequence(),
                'station_id' => $station->id,
                'station_name' => $station->name,
                'status' => MemoStatus::UnderReview->value,
                'current_stage' => $stage->value,
                'created_by' => $administrator->id,
            ]);
            $memo->approvals()->create([
                'role' => $stage->value,
                'sequence' => $stage->sequence(),
                'assigned_name' => $stage->label().' Reviewer',
                'decision' => 'pending',
            ]);
        }

        $response = $this->actingAs($administrator)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Pending memos by approval level')
            ->assertSee('SM level')
            ->assertSee('AM level')
            ->assertSee('Manager level')
            ->assertSee('DGM level');

        foreach (WorkflowStage::cases() as $stage) {
            $response->assertSee('PENDING-'.$stage->sequence())
                ->assertSee(route('sdm-memos.index', ['pending_stage' => $stage->value]), false);
        }

        $this->actingAs($administrator)
            ->get(route('sdm-memos.index', ['pending_stage' => WorkflowStage::LineManager->value]))
            ->assertOk()
            ->assertSee('Pending at AM level')
            ->assertSee('PENDING-2')
            ->assertDontSee('PENDING-1')
            ->assertDontSee('PENDING-3')
            ->assertDontSee('PENDING-4');
    }

    public function test_station_controller_cannot_access_the_dashboard(): void
    {
        $station = Station::query()->create([
            'code' => 'DASH-01',
            'name' => 'Dashboard Station',
            'is_active' => true,
        ]);
        $user = $this->userWithRole(UserRole::Station, ['station_id' => $station->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_station_controllers_do_not_see_the_dashboard_sidebar_link(): void
    {
        $stationController = $this->userWithRole(UserRole::Station);
        $legacyStationController = User::factory()->create();
        $legacyRole = Role::findOrCreate('station_controller');
        $legacyRole->syncPermissions(Role::findByName(UserRole::Station->value)->permissions);
        $legacyStationController->assignRole($legacyRole);
        $administrator = $this->userWithRole(UserRole::Administrator);

        foreach ([$stationController, $legacyStationController] as $user) {
            $this->actingAs($user)
                ->get(route('sdm-memos.index'))
                ->assertOk()
                ->assertDontSee('title="Dashboard"', false)
                ->assertSee('data-station-controller-navigation', false);
        }

        $this->actingAs($administrator)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('title="Dashboard"', false);
    }

    public function test_administrator_can_create_a_station_manager(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $response = $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::StationManager),
            'employee_number' => 'EMP-1001',
        ]);

        $createdUser = User::query()->where('email', 'managed@example.com')->sole();

        $response->assertRedirect(route('users.index', UserRole::StationManager->value));
        $this->assertTrue($createdUser->hasExactRoles(UserRole::StationManager->value));
        $this->assertTrue($createdUser->can(UserPermission::ViewRoutedMemos->value));
        $this->assertTrue($createdUser->can(UserPermission::ActOnRoutedMemos->value));
        $this->assertCount(0, $createdUser->getDirectPermissions());
        $this->assertNotNull($createdUser->email_verified_at);
        $this->assertNull($createdUser->station_id);
        $this->assertTrue(Hash::check('Secure Password 123!', $createdUser->password));
    }

    public function test_station_manager_forms_do_not_show_station_mapping_controls(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::StationManager),
            'name' => 'sm02',
            'email' => 'sm02@example.com',
            'employee_number' => 'EMP-SM02',
        ])->assertRedirect(route('users.index', UserRole::StationManager->value));

        $this->actingAs($administrator)
            ->get(route('users.create', UserRole::StationManager->value))
            ->assertOk()
            ->assertDontSee('name="station_ids[]"', false)
            ->assertDontSee('Stations managed by this Station Manager');

        $stationManager = User::query()->where('email', 'sm02@example.com')->sole();
        $this->actingAs($administrator)
            ->get(route('users.edit', $stationManager))
            ->assertOk()
            ->assertDontSee('name="station_ids[]"', false)
            ->assertDontSee('Stations managed by this Station Manager');

        $this->actingAs($administrator)
            ->get(route('users.index', UserRole::StationManager->value))
            ->assertOk()
            ->assertSee('sm02')
            ->assertDontSee('Station mapping');
    }

    public function test_administrator_has_a_clear_general_create_user_entry_point(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $response = $this->actingAs($administrator)
            ->get(route('users.create_any'))
            ->assertOk()
            ->assertSee('Create user')
            ->assertSee('Station Manager')
            ->assertSee('Assistant Manager')
            ->assertSee('Manager CMC')
            ->assertDontSee('Manager (Operations)');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $roleOptions = (new \DOMXPath($document))->query('//select[@name="role"]/option');
        $roleValues = collect($roleOptions)->map(fn (\DOMElement $option): string => $option->getAttribute('value'));

        $this->assertNotContains(UserRole::Station->value, $roleValues);

        $this->actingAs($administrator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('users.create_any'), false)
            ->assertSee('Create user');

        $managedUser = $this->userWithRole(UserRole::Station);

        $this->actingAs($managedUser)
            ->get(route('users.create_any'))
            ->assertForbidden();
    }

    public function test_cmc_team_lead_create_form_keeps_the_required_name_control_visible_and_can_submit(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $response = $this->actingAs($administrator)
            ->get(route('users.create', UserRole::CmcTeamLead->value))
            ->assertOk()
            ->assertSee('Create CMC SM')
            ->assertDontSee('CMC Team Lead');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $nameInput = (new \DOMXPath($document))->query('//input[@name="name"]')->item(0);

        $this->assertNotNull($nameInput);
        for ($ancestor = $nameInput->parentNode; $ancestor?->nodeName !== 'form'; $ancestor = $ancestor->parentNode) {
            $this->assertFalse($ancestor instanceof \DOMElement && $ancestor->hasAttribute('x-show'));
        }

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::CmcTeamLead),
            'employee_number' => 'EMP-CMC-TL-01',
        ])->assertRedirect(route('users.index', UserRole::CmcTeamLead->value));

        $cmcTeamLead = User::query()->where('email', 'managed@example.com')->sole();
        $this->assertTrue($cmcTeamLead->hasExactRoles(UserRole::CmcTeamLead->value));
    }

    public function test_role_specific_personnel_and_station_rules_are_enforced(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)
            ->post(route('users.store'), $this->validUserData(UserRole::StationManager))
            ->assertSessionHasErrors(['employee_number'])
            ->assertSessionDoesntHaveErrors(['station_ids']);

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
            'designation' => null,
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

    public function test_username_must_be_unique_and_may_contain_integers(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $existingUser = $this->userWithRole(UserRole::Station, [
            'name' => 'Central Station',
        ]);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::Station),
            'name' => $existingUser->name,
        ])->assertSessionHasErrors('name');

        foreach (['Administrator 1', '12345'] as $validName) {
            $this->actingAs($administrator)->post(route('users.store'), [
                ...$this->validUserData(UserRole::AssistantManager),
                'name' => $validName,
                'email' => Str::of($validName)->remove(' ')->lower().'@example.com',
                'employee_number' => 'EMP-'.$validName,
            ])->assertSessionDoesntHaveErrors('name');
        }

        $managedUser = User::query()->where('name', 'Administrator 1')->sole();
        $this->actingAs($administrator)->put(route('users.update', $managedUser), [
            ...$this->validUserData(UserRole::AssistantManager),
            'name' => 'Administrator 2026',
            'email' => $managedUser->email,
            'employee_number' => $managedUser->employee_number,
            'password' => '',
            'password_confirmation' => '',
        ])->assertSessionDoesntHaveErrors('name');
        $this->assertSame('Administrator 2026', $managedUser->fresh()->name);

        foreach (['station_12', 'person@example.com'] as $invalidName) {
            $this->actingAs($administrator)->post(route('users.store'), [
                ...$this->validUserData(UserRole::Station),
                'name' => $invalidName,
            ])->assertSessionHasErrors('name');
        }
    }

    public function test_line_and_station_are_optional_when_creating_any_user_role(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $redLine = Line::query()->create(['code' => 'RED', 'name' => 'Red Line', 'is_active' => true]);
        $blueLine = Line::query()->create(['code' => 'BLUE', 'name' => 'Blue Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'RED-01',
            'name' => 'Red Station',
            'line_id' => $redLine->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('users.create', UserRole::Station->value))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertFalse($xpath->query('//select[@name="line_id"]')->item(0)->hasAttribute('required'));
        $this->assertFalse($xpath->query('//select[@name="station_id"]')->item(0)->hasAttribute('required'));

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::Station),
        ])->assertRedirect(route('users.index', UserRole::Station->value));

        $createdUser = User::query()->where('email', 'managed@example.com')->sole();
        $this->assertNull($createdUser->station_id);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::Station),
            'name' => 'Mapped Station Controller',
            'email' => 'mapped@example.com',
            'line_id' => $redLine->id,
            'station_id' => $station->id,
        ])->assertRedirect(route('users.index', UserRole::Station->value));

        $mappedUser = User::query()->where('email', 'mapped@example.com')->sole();
        $this->assertSame($station->id, $mappedUser->station_id);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(UserRole::AssistantManager),
            'name' => 'Invalid Mapping',
            'email' => 'invalid-mapping@example.com',
            'employee_number' => 'EMP-INVALID-MAPPING',
            'line_id' => $blueLine->id,
            'station_id' => $station->id,
        ])->assertSessionHasErrors('station_id');
    }

    public function test_custom_memo_creator_role_may_be_created_without_a_station_assignment(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $line = Line::query()->create(['code' => 'YELLOW', 'name' => 'Yellow Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'YELLOW-01',
            'name' => 'Yellow Station',
            'line_id' => $line->id,
            'is_active' => true,
        ]);
        $role = Role::findOrCreate('station_controller');
        $role->givePermissionTo([
            UserPermission::CreateMemos->value,
            UserPermission::ViewOwnMemos->value,
            UserPermission::UpdateOwnDraftMemos->value,
            UserPermission::SubmitOwnDraftMemos->value,
        ]);

        $response = $this->actingAs($administrator)
            ->get(route('users.create', $role->name))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $form = (new \DOMXPath($document))->query('//form[@data-direct-station-roles]')->item(0);

        $this->assertInstanceOf(\DOMElement::class, $form);
        $this->assertContains($role->name, json_decode($form->getAttribute('data-direct-station-roles'), true, flags: JSON_THROW_ON_ERROR));

        $this->actingAs($administrator)->post(route('users.store'), [
            'name' => 'Station Controller',
            'email' => 'station-controller@example.com',
            'role' => $role->name,
            'employee_number' => 'EMP-SC-01',
            'phone' => null,
            'designation' => null,
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ])->assertRedirect(route('users.index', $role->name));

        $stationController = User::query()->where('email', 'station-controller@example.com')->sole();

        $this->assertNull($stationController->station_id);
        $this->assertTrue($stationController->can('create', ServiceDeficiencyMemo::class));

        $editResponse = $this->actingAs($administrator)
            ->get(route('users.edit', $stationController))
            ->assertOk();
        $editDocument = new \DOMDocument;
        @$editDocument->loadHTML($editResponse->getContent());
        $editForm = (new \DOMXPath($editDocument))->query('//form[@data-direct-station-roles]')->item(0);

        $this->assertInstanceOf(\DOMElement::class, $editForm);
        $this->assertContains($role->name, json_decode($editForm->getAttribute('data-direct-station-roles'), true, flags: JSON_THROW_ON_ERROR));

        $this->actingAs($stationController)
            ->get(route('dashboard'))
            ->assertForbidden();
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
