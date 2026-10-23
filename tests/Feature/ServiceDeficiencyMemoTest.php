<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Support\PenaltyClauseTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceDeficiencyMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_role_receives_memo_permissions_and_administrator_receives_all_permissions(): void
    {
        [$stationUser, , $station] = $this->stationUserWithLine();
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->assertTrue($stationUser->can(UserPermission::CreateMemos->value));
        $this->assertFalse($stationUser->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($stationManager->can(UserPermission::CreateMemos->value));
        $this->assertTrue($stationManager->can('create', ServiceDeficiencyMemo::class));
        $this->assertTrue($administrator->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($administrator->can(UserPermission::ManageLines->value));

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Create SDM memo');

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($station->code.' · '.$station->name);
    }

    public function test_station_manager_can_create_and_submit_a_memo_for_the_station_selected_at_login(): void
    {
        [, $line, $station] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $lineManager = User::query()->role(UserRole::AssistantManager->value)->firstOrFail();
        $inactiveLineManager = User::factory()->disabled()->create(['employee_number' => 'AM-INACTIVE']);
        $inactiveLineManager->assignRole(UserRole::AssistantManager->value);
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->post(route('login'), [
            'username' => $stationManager->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($line->name)
            ->assertSee($station->code.' · '.$station->name)
            ->assertSee('name="line_manager_employee_number"', false)
            ->assertSee('x-data="employeeAutocomplete"', false)
            ->assertSee('data-candidates=', false)
            ->assertSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertDontSee($inactiveLineManager->employee_number.' - '.$inactiveLineManager->name)
            ->assertDontSee('name="station_manager_employee_number"', false);

        $memoData = [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-STAFF-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Misbehavior->value],
            'deficiency_remarks' => [DeficiencyType::Misbehavior->value => 'Test incident'],
        ];

        $this->post(route('sdm-memos.store-and-submit'), $memoData)
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $inactiveLineManager->employee_number])
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $stationManager->employee_number])
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $lineManager->employee_number])
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->whereNotNull('submitted_at')->sole();
        $this->assertSame($station->id, $memo->station_id);
        $this->assertSame($line->name, $memo->line_name);
        $this->assertSame(MemoStatus::UnderReview, $memo->status);
        $this->assertSame(WorkflowStage::LineManager, $memo->current_stage);
        $this->assertSame($lineManager->id, $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to'));
    }

    public function test_station_login_selection_defaults_the_create_form_station_and_line(): void
    {
        [$user] = $this->stationUserWithLine();
        $line = Line::query()->create(['code' => 'BLUE', 'name' => 'Blue Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'BLUE-01', 'name' => 'Blue Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password'])
            ->assertSessionHasErrors('station_id');

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password', 'station_id' => $station->id])
            ->assertRedirect(route('sdm-memos.create', absolute: false))
            ->assertSessionHas('auth.station_id', $station->id);
        $this->assertAuthenticatedAs($user);

        $this->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($line->code.' · '.$line->name)
            ->assertSee($station->code.' · '.$station->name);
    }

    public function test_create_form_displays_the_line_code_and_station_name_beside_memo_details(): void
    {
        [$user, $line, $station] = $this->stationUserWithLine();

        $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Memo details',
                'data-memo-location',
                '['.$line->code.' · '.$station->name.']',
            ], false);
    }

    public function test_cash_check_official_fields_default_to_the_logged_in_user_and_remain_editable(): void
    {
        [$user] = $this->stationUserWithLine();
        $user->update([
            'name' => 'Inspecting Officer',
            'designation' => 'Station Controller',
            'employee_number' => 'EMP-INSPECT-01',
        ]);

        $response = $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        foreach ([
            'cash_check[checking_official_name]' => $user->name,
            'cash_check[checking_official_designation]' => $user->designation,
            'cash_check[checking_official_employee_number]' => $user->employee_number,
        ] as $name => $expectedValue) {
            $input = $xpath->query(sprintf('//input[@name="%s"]', $name))->item(0);

            $this->assertInstanceOf(\DOMElement::class, $input);
            $this->assertSame($expectedValue, $input->getAttribute('value'));
            $this->assertFalse($input->hasAttribute('readonly'));
            $this->assertFalse($input->hasAttribute('disabled'));
        }
    }

    public function test_create_form_provides_every_ssc_penalty_clause_for_each_sdm_type(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('data-deficiency-options=', false)
            ->assertViewHas('deficiencyOptionsByType', function (array $optionsByType): bool {
                foreach (SdmType::cases() as $type) {
                    $expectedDescriptions = collect(PenaltyClauseTemplates::for($type))
                        ->pluck('description')
                        ->push(DeficiencyType::Other->label())
                        ->all();
                    $options = collect($optionsByType[$type->value] ?? []);

                    if ($options->pluck('label')->all() !== $expectedDescriptions) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_a_deficiency_must_belong_to_the_selected_sdm_type(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'deficiencies' => ['undervalued_tickets'],
        ])->assertSessionHasErrors('deficiencies.0');

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => ['undervalued_tickets'],
        ])->assertRedirect();

        $this->assertSame('undervalued_tickets', ServiceDeficiencyMemo::query()->sole()->deficiencies()->sole()->type->value);
    }

    public function test_station_user_can_save_partial_draft_but_submission_is_strict(): void
    {
        [$user] = $this->stationUserWithLine();

        $response = $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-100',
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertSame(MemoStatus::Draft, $memo->status);

        $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-100',
        ])->assertSessionHasErrors(['contractor_id', 'incident_date', 'incident_time', 'staff_name', 'employee_number', 'deficiencies']);
    }

    public function test_station_controller_can_create_a_draft_without_a_serial_number(): void
    {
        [$stationController] = $this->stationUserWithLine();

        $response = $this->actingAs($stationController)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();

        $response->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertNull($memo->serial_number);
    }

    public function test_creator_can_delete_a_draft_and_its_stored_attachments(): void
    {
        Storage::fake('local');
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-DELETE-1',
            'attachments' => [UploadedFile::fake()->create('draft-proof.pdf', 25, 'application/pdf')],
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $attachmentPath = $memo->attachments()->sole()->storage_path;
        Storage::disk('local')->assertExists($attachmentPath);

        $this->actingAs($user)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('Delete draft')
            ->assertSee(route('sdm-memos.destroy', $memo), false);

        $this->actingAs($user)->delete(route('sdm-memos.destroy', $memo))
            ->assertRedirect(route('sdm-memos.drafts'))
            ->assertSessionHas('status', 'Draft deleted.');

        $this->assertNull(ServiceDeficiencyMemo::withTrashed()->find($memo->id));
        Storage::disk('local')->assertMissing($attachmentPath);
    }

    public function test_station_manager_can_delete_their_own_draft(): void
    {
        [, , $station] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'OWN-SM-DRAFT',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => MemoStatus::Draft->value,
            'created_by' => $stationManager->id,
        ]);

        $otherStationManager = User::factory()->create();
        $otherStationManager->assignRole(UserRole::StationManager->value);
        ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'OTHER-SM-DRAFT',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => MemoStatus::Draft->value,
            'created_by' => $otherStationManager->id,
        ]);

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('sdm-memos.index', ['status' => 'drafts_own']))
            ->assertOk()
            ->assertSee('OWN-SM-DRAFT')
            ->assertDontSee('OTHER-SM-DRAFT')
            ->assertSee('href="'.route('sdm-memos.edit', $memo).'"', false)
            ->assertSee(route('sdm-memos.destroy', $memo), false)
            ->assertSee('Cancel');

        $this->actingAs($stationManager)
            ->delete(route('sdm-memos.destroy', $memo))
            ->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHas('status', 'Draft deleted.');

        $this->assertNull(ServiceDeficiencyMemo::withTrashed()->find($memo->id));
    }

    public function test_another_user_and_the_creator_after_submission_cannot_delete_the_memo(): void
    {
        [$owner] = $this->stationUserWithLine('RED');
        [$other] = $this->stationUserWithLine('GREEN');
        $memo = $this->draft($owner, SdmType::Cfs, 'CFS-DELETE-2');

        $this->actingAs($other)->delete(route('sdm-memos.destroy', $memo))->assertForbidden();
        $this->assertModelExists($memo);

        $memo->update(['status' => MemoStatus::Submitted->value]);

        $this->actingAs($owner)->delete(route('sdm-memos.destroy', $memo))->assertForbidden();
        $this->assertModelExists($memo);
    }

    public function test_station_user_can_submit_a_complete_memo_without_saving_a_draft_first(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-10',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Other service deficiency.'],
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.index'));
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertNotNull($memo->submitted_at);
        $this->assertSame($user->id, $memo->created_by);
        $this->assertSame('14:25', $memo->incident_time);
        $this->assertSame('RED/RED-01/CFA/2026/09/01', $memo->serial_number);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Incident time (IST)')
            ->assertSee('14:25');
    }

    public function test_submission_requires_outsourced_staff_acknowledgement(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-ACKNOWLEDGEMENT',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'deficiencies' => [DeficiencyType::Other->value],
        ])->assertSessionHasErrors('outsourced_staff_acknowledged');

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    public function test_submission_generates_a_unique_monthly_sdm_serial_number(): void
    {
        Carbon::setTestNow('2026-09-25 10:30:00');

        [$firstUser, $firstLine, $firstStation] = $this->stationUserWithLine('L-1E');
        $firstStation->update(['code' => 'RHE']);
        [$secondUser, $secondLine, $secondStation] = $this->stationUserWithLine('L-5');
        $secondStation->update(['code' => 'APMN']);
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($firstUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '25-09-2026',
            'incident_time' => '09:00',
            'staff_name' => 'First Staff Member',
            'employee_number' => 'EMP-101',
            'station_manager_employee_number' => 'L-1E-SM-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'First deficiency.'],
        ])->assertRedirect(route('sdm-memos.index'));

        $this->actingAs($secondUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Ticketing->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '25-09-2026',
            'incident_time' => '09:15',
            'staff_name' => 'Second Staff Member',
            'employee_number' => 'EMP-102',
            'station_manager_employee_number' => 'L-5-SM-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Second deficiency.'],
        ])->assertRedirect(route('sdm-memos.index'));

        $this->assertSame('L-1E', $firstLine->code);
        $this->assertSame('L-5', $secondLine->code);
        $this->assertSame([
            'L-1E/RHE/CFA/2026/09/01',
            'L-5/APMN/TVS/2026/09/02',
        ], ServiceDeficiencyMemo::query()->orderBy('id')->pluck('serial_number')->all());
    }

    public function test_memo_details_display_the_line_code_and_name_separated_by_a_comma(): void
    {
        [$user, $line] = $this->stationUserWithLine('RED');
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-LINE-1');
        $line->update(['code' => 'BLUE', 'name' => 'Blue Line']);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('RED, RED Line');
    }

    public function test_memo_details_display_the_late_reporting_delay_in_minutes(): void
    {
        [$user] = $this->stationUserWithLine();
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-DELAY-1');
        $deficiency = $memo->deficiencies()->create(['type' => DeficiencyType::LateReporting->value]);
        $deficiency->lateReporting()->create([
            'booked_shift_from' => '09:00',
            'booked_shift_to' => '17:00',
            'actual_reporting_time' => '09:25',
        ]);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Delay: 25 minutes');

        $deficiency->lateReporting->update(['actual_reporting_time' => '10:25']);
        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Delay: 1 hour 25 minutes');
    }

    public function test_complete_ticketing_memo_persists_conditional_sections_and_private_attachment(): void
    {
        Storage::fake('local');
        [$user, , $station] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);
        $complaintStation = Station::query()->create(['code' => 'CMP', 'name' => 'Complaint Station', 'line_id' => $station->line_id, 'is_active' => true]);
        $memo = $this->draft($user, SdmType::Ticketing, 'TV-100');

        $response = $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-100',
            'contractor_id' => $contractor->id,
            'incident_date' => '17-08-2026',
            'incident_time' => '21:35',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-9',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value, DeficiencyType::DressCode->value, DeficiencyType::CashCheck->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
            'late_reporting' => ['booked_shift_from' => '09:00', 'booked_shift_to' => '17:00', 'actual_reporting_time' => '09:25'],
            'public_complaint' => ['number' => 'PC-10', 'station_id' => $complaintStation->id],
            'cash_check' => [
                'tom_ccc_number' => 'TOM-2',
                'end_of_shift_receipt_number' => 'EOS-25',
                'check_date' => '2026-08-17',
                'check_time' => '21:35',
                'difference_amount' => '-50.00',
                'hidden_cash_amount' => '100.00',
                'checking_official_name' => 'Checking Officer',
                'checking_official_designation' => 'Manager',
                'checking_official_employee_number' => 'EMP-55',
                'remarks' => 'Verified at the end of shift.',
            ],
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
        ]);

        $response->assertRedirect(route('sdm-memos.index'));
        $memo->refresh();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame('Metro Services', $memo->contractor_name);
        $this->assertCount(3, $memo->deficiencies);
        $this->assertSame('PC-10', $memo->publicComplaint->complaint_serial_number);
        $this->assertSame('-50.00', $memo->cashCheck->difference_amount);
        $this->assertSame('17-08-2026 21:35', $memo->cashCheck->checked_at->format('d-m-Y H:i'));
        $this->assertSame('Checking Officer', $memo->cashCheck->checking_official_name);
        $this->assertSame('100.00', $memo->cashCheck->hidden_cash_amount);
        Storage::disk('local')->assertExists($memo->attachments()->sole()->storage_path);
    }

    public function test_saved_late_reporting_times_can_be_resubmitted_with_database_seconds(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-LATE-REPORTING');
        $deficiency = $memo->deficiencies()->create(['type' => DeficiencyType::LateReporting->value]);
        $deficiency->lateReporting()->create([
            'booked_shift_from' => '09:00:00',
            'booked_shift_to' => '17:00:00',
            'actual_reporting_time' => '09:25:00',
        ]);

        $this->actingAs($user)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('value="09:00"', false)
            ->assertDontSee('value="09:00:00"', false);

        $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-LATE-REPORTING',
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '09:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-LATE',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value],
            'late_reporting' => [
                'booked_shift_from' => '09:00:00',
                'booked_shift_to' => '17:00:00',
                'actual_reporting_time' => '09:25:00',
            ],
        ])->assertRedirect(route('sdm-memos.index'));

        $memo->refresh();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame('09:25', $memo->deficiencies()->sole()->lateReporting->actual_reporting_time);
    }

    public function test_ticketing_late_reporting_submission_ignores_hidden_cash_check_defaults(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-LATE-REPORTING',
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '09:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-LATE-TV',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value],
            'late_reporting' => [
                'booked_shift_from' => '09:00',
                'booked_shift_to' => '17:00',
                'actual_reporting_time' => '09:25',
            ],
            'cash_check' => [
                'check_date' => '2026-08-18',
                'check_time' => null,
            ],
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertNull($memo->cashCheck);
    }

    public function test_create_form_exposes_visible_multi_selection_and_requested_cash_check_fields(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('<title>Create Service Deficiency Memo</title>', false)
            ->assertSee('RED · RED Line')
            ->assertSee('data-selected-deficiencies', false)
            ->assertSee(route('sdm-memos.store-and-submit'), false)
            ->assertSee('Submit memo')
            ->assertSee('data-sdm-confirm', false)
            ->assertSee('Are you sure you want to proceed?')
            ->assertDontSee('SDM number')
            ->assertDontSee('name="serial_number"', false)
            ->assertSee('Proceed')
            ->assertSee('id="incident_date" type="date" name="incident_date"', false)
            ->assertSee('Time of incident (HH:MM, IST)')
            ->assertSee('id="incident_time" type="time" name="incident_time"', false)
            ->assertSee('value="15:30"', false)
            ->assertSee('required', false)
            ->assertSee('cash_check[check_date]', false)
            ->assertSee('type="date"', false)
            ->assertSee('max="2026-08-18"', false)
            ->assertSee('value="2026-08-18"', false)
            ->assertSee('cash_check[check_time]', false)
            ->assertSee('Cash-check remarks (if any)')
            ->assertDontSee('cash_check[operator_name]', false)
            ->assertDontSee('cash_check[checked_at]', false);
    }

    public function test_create_form_always_shows_one_optional_general_remarks_field(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Remarks (optional)')
            ->assertSee('name="station_record_brief_remarks"', false)
            ->assertDontSee('name="deficiency_remarks', false)
            ->assertDontSee('Poor Dress Code / Failure to Follow Instructions remarks')
            ->assertDontSee('Misbehavior with Customer / Staff remarks');
    }

    public function test_dress_code_and_misbehavior_can_be_submitted_without_deficiency_specific_remarks(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-OPTIONAL-REMARKS',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [
                DeficiencyType::DressCode->value,
                DeficiencyType::Misbehavior->value,
            ],
            'station_record_brief_remarks' => 'General optional context for the SDM.',
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();

        $response->assertRedirect(route('sdm-memos.index'));
        $this->assertSame('General optional context for the SDM.', $memo->station_record_brief_remarks);
        $this->assertTrue($memo->deficiencies->every(fn ($deficiency): bool => $deficiency->remarks === null));
    }

    public function test_create_form_shows_the_staff_acknowledgement_and_conditionally_displays_complaint_documents(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Deficiencies',
                'I have received acknowledgement from outsourced staff',
                'Complaint and supporting documents',
            ])
            ->assertSeeInOrder([
                'id="outsourced_staff_acknowledged"',
                'name="outsourced_staff_acknowledged"',
                'type="checkbox"',
                'value="1"',
                'required',
                'aria-required="true"',
            ], false)
            ->assertSee('formnovalidate', false)
            ->assertSee('x-show="has(\'public_complaint\')"', false)
            ->assertSee('x-cloak', false);
    }

    public function test_station_controller_create_form_has_a_clear_submit_forwarding_prompt(): void
    {
        [$user] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $lineManager = User::query()->role(UserRole::AssistantManager->value)->firstOrFail();
        $inactiveStationManager = User::factory()->disabled()->create(['employee_number' => 'SM-INACTIVE']);
        $inactiveStationManager->assignRole(UserRole::StationManager->value);

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Forwarding on submission')
            ->assertSee('Enter the employee number of the SM who should receive this SDM immediately after submission.')
            ->assertSee('name="station_manager_employee_number"', false)
            ->assertSee('data-employee-autocomplete', false)
            ->assertSee($stationManager->employee_number.' - '.$stationManager->name)
            ->assertDontSee($inactiveStationManager->employee_number.' - '.$inactiveStationManager->name)
            ->assertDontSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertSee(route('sdm-memos.store-and-submit'), false);
    }

    public function test_custom_station_controller_role_sees_the_submit_forwarding_prompt(): void
    {
        [, , $station] = $this->stationUserWithLine();
        $role = Role::findOrCreate('station_controller');
        $role->givePermissionTo([
            UserPermission::CreateMemos->value,
            UserPermission::ViewOwnMemos->value,
            UserPermission::UpdateOwnDraftMemos->value,
            UserPermission::SubmitOwnDraftMemos->value,
        ]);
        $controller = User::factory()->create(['station_id' => $station->id]);
        $controller->assignRole($role->name);

        $this->actingAs($controller)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Forwarding on submission')
            ->assertSee('name="station_manager_employee_number"', false);
    }

    public function test_late_reporting_uses_custom_times_and_displays_the_calculated_delay(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertDontSee('Morning shift')
            ->assertDontSee('Evening shift')
            ->assertDontSee('name="late_reporting_shift_preset"', false)
            ->assertSee('x-model="bookedShiftFrom"', false)
            ->assertSee('x-model="actualReportingTime"', false)
            ->assertSee('x-text="delayLabel"', false)
            ->assertSee('late_reporting[booked_shift_from]', false)
            ->assertSee('late_reporting[booked_shift_to]', false);
    }

    public function test_operator_phone_and_signature_upload_are_not_shown_or_required(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertDontSee('name="staff_mobile"', false)
            ->assertDontSee('name="staff_signature"', false);
    }

    public function test_cash_check_date_cannot_be_after_the_current_date(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-FUTURE-CASH-CHECK',
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => [
                'check_date' => '2026-08-19',
                'check_time' => '12:30',
            ],
        ])->assertSessionHasErrors('cash_check.check_date');

        $this->assertDatabaseMissing('service_deficiency_memos', [
            'serial_number' => 'TV-FUTURE-CASH-CHECK',
        ]);
    }

    public function test_serial_number_is_visible_in_the_owners_submitted_list_and_to_authorized_viewers(): void
    {
        [$owner] = $this->stationUserWithLine('RED');
        [$other] = $this->stationUserWithLine('GREEN');
        $viewerWithoutMemoPermissions = User::factory()->create();
        $memo = $this->draft($owner, SdmType::Cfs, 'SAME-1');

        $memo->update(['status' => MemoStatus::Submitted->value, 'submitted_at' => now()]);
        $this->actingAs($owner)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($other)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);
        $this->actingAs($other)->get(route('sdm-memos.show', $memo))->assertForbidden();
        $this->actingAs($other)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('dashboard'))->assertOk()->assertSee('All memos');
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSeeInOrder(['SDM Serial Number:', $memo->serial_number]);
    }

    public function test_draft_is_visible_only_to_the_user_who_created_it(): void
    {
        [$creator] = $this->stationUserWithLine('DRAFT-OWNER');
        $draft = $this->draft($creator, SdmType::Cfs, 'PRIVATE-DRAFT-101');
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->doesntContain('id', $draft->id))
            ->assertDontSee($draft->serial_number);

        $this->actingAs($administrator)
            ->get(route('sdm-memos.show', $draft))
            ->assertForbidden();

        $this->actingAs($creator)
            ->get(route('sdm-memos.edit', $draft))
            ->assertOk();
    }

    public function test_memo_index_can_filter_by_type_station_line_incident_date_and_status(): void
    {
        [$user, $targetLine, $targetStation] = $this->stationUserWithLine('FILTER-RED');
        [$otherUser] = $this->stationUserWithLine('FILTER-GREEN');
        $viewer = User::factory()->create();
        $target = $this->draft($user, SdmType::Cfs, 'FILTER-TARGET-101');
        $target->update([
            'incident_date' => '2026-09-10',
            'status' => MemoStatus::Submitted->value,
        ]);
        $other = $this->draft($otherUser, SdmType::Ticketing, 'OTHER-MEMO-202');
        $other->update(['incident_date' => '2026-09-11']);

        $this->actingAs($viewer)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('name="service_type"', false)
            ->assertDontSee('name="serial_number"', false)
            ->assertSee('name="station_id"', false)
            ->assertSee('name="line_code"', false)
            ->assertSee('name="incident_date"', false)
            ->assertSee('name="status"', false);

        $filters = [
            ['service_type' => SdmType::Cfs->value],
            ['station_id' => $targetStation->id],
            ['line_code' => $targetLine->code],
            ['incident_date' => '2026-09-10'],
            ['status' => MemoStatus::Submitted->value],
        ];

        foreach ($filters as $filter) {
            $this->actingAs($viewer)->get(route('sdm-memos.index', $filter))
                ->assertOk()
                ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$target->id])
                ->assertDontSee($target->serial_number)
                ->assertDontSee($other->serial_number);
        }
    }

    public function test_administrator_can_manage_master_data_and_referenced_records_cannot_be_deleted(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)->post(route('lines.store'), ['code' => 'YELLOW', 'name' => 'Yellow Line', 'is_active' => '1'])->assertRedirect();
        $line = Line::query()->where('code', 'YELLOW')->sole();
        Station::query()->create(['code' => 'YS1', 'name' => 'Yellow Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->actingAs($administrator)->delete(route('lines.destroy', $line))->assertSessionHasErrors('line');
        $this->assertModelExists($line);
    }

    public function test_line_names_may_repeat_but_line_codes_must_remain_unique(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-1',
            'name' => 'Blue Line',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-2',
            'name' => 'Blue Line',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Line::query()->where('name', 'Blue Line')->count());

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-1',
            'name' => 'Another Blue Line',
            'is_active' => '1',
        ])->assertSessionHasErrors('code');
    }

    public function test_existing_stations_are_displayed_linewise(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $zuluCodeLine = Line::query()->create(['code' => 'ZULU', 'name' => 'Alpha Line', 'is_active' => true]);
        $alphaCodeLine = Line::query()->create(['code' => 'ALPHA', 'name' => 'Zulu Line', 'is_active' => true]);

        Station::query()->create(['code' => 'ZULU-02', 'name' => 'Zulu Second', 'line_id' => $zuluCodeLine->id, 'is_active' => true]);
        Station::query()->create(['code' => 'ALPHA-01', 'name' => 'Alpha First', 'line_id' => $alphaCodeLine->id, 'is_active' => true]);
        Station::query()->create(['code' => 'ZULU-01', 'name' => 'Zulu First', 'line_id' => $zuluCodeLine->id, 'is_active' => true]);

        $this->actingAs($administrator)->get(route('stations.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-line-code="ALPHA"',
                'Alpha First',
                'data-line-code="ZULU"',
                'Zulu First',
                'Zulu Second',
            ], false);
    }

    private function stationUser(): User
    {
        [$user] = $this->stationUserWithLine();

        return $user;
    }

    private function stationUserWithLine(string $code = 'RED'): array
    {
        $line = Line::query()->create(['code' => $code, 'name' => $code.' Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => $code.'-01', 'name' => $code.' Station', 'line_id' => $line->id, 'is_active' => true]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $user->assignRole(UserRole::Station->value);
        $stationManager = User::factory()->create(['employee_number' => $code.'-SM-1']);
        $stationManager->assignRole(UserRole::StationManager->value);
        $lineManager = User::factory()->create(['employee_number' => $code.'-AM-1']);
        $lineManager->assignRole(UserRole::AssistantManager->value);
        if (! User::query()->role(UserRole::ManagerOperations->value)->exists()) {
            $operationsManager = User::factory()->create();
            $operationsManager->assignRole(UserRole::ManagerOperations->value);
        }

        if (! User::query()->role(UserRole::DeputyGeneralManager->value)->exists()) {
            $dgm = User::factory()->create();
            $dgm->assignRole(UserRole::DeputyGeneralManager->value);
        }

        return [$user, $line, $station];
    }

    private function draft(User $user, SdmType $type, string $serial): ServiceDeficiencyMemo
    {
        $this->actingAs($user)->post(route('sdm-memos.store'), ['service_type' => $type->value])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->whereBelongsTo($user, 'creator')->latest('id')->firstOrFail();
        $memo->update(['serial_number' => $serial]);

        return $memo;
    }
}
