<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServiceDeficiencyMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_role_receives_memo_permissions_and_administrator_receives_all_permissions(): void
    {
        $stationUser = $this->stationUser();
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->assertTrue($stationUser->can(UserPermission::CreateMemos->value));
        $this->assertFalse($stationUser->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($administrator->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($administrator->can(UserPermission::ManageLines->value));
    }

    public function test_station_login_uses_the_users_assigned_station_without_line_input(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
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
        ])->assertSessionHasErrors(['contractor_id', 'incident_date', 'staff_name', 'employee_number', 'deficiencies']);
    }

    public function test_station_user_can_submit_a_complete_memo_without_saving_a_draft_first(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-101',
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-10',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Other service deficiency.'],
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.show', $memo));
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertNotNull($memo->submitted_at);
        $this->assertSame($user->id, $memo->created_by);
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
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-9',
            'deficiencies' => [DeficiencyType::LateReporting->value, DeficiencyType::DressCode->value, DeficiencyType::CashCheck->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
            'late_reporting' => ['booked_shift_from' => '09:00', 'booked_shift_to' => '17:00', 'actual_reporting_time' => '09:25'],
            'public_complaint' => ['number' => 'PC-10', 'station_id' => $complaintStation->id],
            'cash_check' => [
                'tom_ccc_number' => 'TOM-2',
                'end_of_shift_receipt_number' => 'EOS-25',
                'check_date' => '17-08-2026',
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

        $response->assertRedirect(route('sdm-memos.show', $memo));
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

    public function test_create_form_exposes_visible_multi_selection_and_requested_cash_check_fields(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('data-selected-deficiencies', false)
            ->assertSee(route('sdm-memos.store-and-submit'), false)
            ->assertSee('Submit memo')
            ->assertSee('cash_check[check_date]', false)
            ->assertSee('cash_check[check_time]', false)
            ->assertSee('Cash-check remarks (if any)')
            ->assertDontSee('cash_check[operator_name]', false)
            ->assertDontSee('cash_check[checked_at]', false);
    }

    public function test_serial_number_is_unique_per_sdm_type_and_users_cannot_access_another_creators_memo(): void
    {
        [$owner] = $this->stationUserWithLine('RED');
        [$other] = $this->stationUserWithLine('GREEN');
        $memo = $this->draft($owner, SdmType::Cfs, 'SAME-1');

        $this->actingAs($owner)->post(route('sdm-memos.store'), ['service_type' => SdmType::Cfs->value, 'serial_number' => 'SAME-1'])
            ->assertSessionHasErrors('serial_number');
        $this->actingAs($owner)->post(route('sdm-memos.store'), ['service_type' => SdmType::Ticketing->value, 'serial_number' => 'SAME-1'])
            ->assertRedirect();
        $this->actingAs($other)->get(route('sdm-memos.show', $memo))->assertForbidden();
        $this->actingAs($other)->get(route('sdm-memos.edit', $memo))->assertForbidden();
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

        return [$user, $line, $station];
    }

    private function draft(User $user, SdmType $type, string $serial): ServiceDeficiencyMemo
    {
        $this->actingAs($user)->post(route('sdm-memos.store'), ['service_type' => $type->value, 'serial_number' => $serial])->assertRedirect();

        return ServiceDeficiencyMemo::query()->where('serial_number', $serial)->sole();
    }
}
