<?php

namespace Tests\Feature;

use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\OutsourcedStaff;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SdmOutsourcedStaffLookupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_station_user_can_find_staff_by_employee_id_or_aadhaar_without_receiving_private_details(): void
    {
        $staff = OutsourcedStaff::factory()->create(['employee_id' => '900001', 'name' => 'Registered staff', 'aadhaar_number' => '012345678901']);
        $this->actingAs($this->creator());

        foreach (['900001', '012345678901', '0123 4567 8901'] as $identifier) {
            $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => $identifier, 'contractor_id' => $staff->contractor_id])
                ->assertOk()->assertExactJson(['status' => 'found', 'employee_number' => '900001', 'staff_name' => 'Registered staff'])
                ->assertHeader('Cache-Control', 'no-store, private');
        }

        $this->assertStringNotContainsString('012345678901', AuditLog::query()->get()->toJson());
    }

    public function test_lookup_is_exact_and_scoped_to_the_selected_contractor(): void
    {
        $staff = OutsourcedStaff::factory()->create(['employee_id' => '900001', 'aadhaar_number' => '012345678901']);
        $other = Contractor::factory()->create();
        $this->actingAs($this->creator());

        foreach (['900001', '012345678901'] as $identifier) {
            $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => $identifier, 'contractor_id' => $other->id])
                ->assertOk()->assertExactJson(['status' => 'missing']);
        }
        $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => '900', 'contractor_id' => $staff->contractor_id])
            ->assertOk()->assertExactJson(['status' => 'missing']);
    }

    public function test_existing_inactive_staff_can_still_be_identified_for_an_incident(): void
    {
        $staff = OutsourcedStaff::factory()->create(['is_active' => false]);

        $this->actingAs($this->creator())->postJson(route('sdm-memos.staff-lookup'), [
            'staff_identifier' => $staff->employee_id, 'contractor_id' => $staff->contractor_id,
        ])->assertOk()->assertJsonPath('staff_name', $staff->name);
    }

    public function test_duplicate_aadhaar_matches_require_an_employee_id_instead_of_selecting_a_person_arbitrarily(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);
        OutsourcedStaff::factory()->for($staff->contractor)->create(['aadhaar_number' => '012345678901']);

        $this->actingAs($this->creator())->postJson(route('sdm-memos.staff-lookup'), [
            'staff_identifier' => '012345678901', 'contractor_id' => $staff->contractor_id,
        ])->assertOk()->assertExactJson(['status' => 'ambiguous']);
    }

    public function test_lookup_requires_authentication_memo_creation_permission_and_a_valid_payload(): void
    {
        $this->postJson('/sdm-memos/staff-lookup')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson('/sdm-memos/staff-lookup')->assertForbidden();
        $this->actingAs(User::factory()->disabled()->create())->postJson('/sdm-memos/staff-lookup')->assertRedirect(route('login'));
        $this->actingAs($this->creator())->postJson('/sdm-memos/staff-lookup', ['staff_identifier' => ['unexpected'], 'contractor_id' => 99999])
            ->assertUnprocessable()->assertJsonValidationErrors(['staff_identifier', 'contractor_id']);
    }

    public function test_saving_with_aadhaar_uses_the_registered_employee_id_and_name_even_if_the_browser_name_is_changed(): void
    {
        $actor = $this->creator();
        $staff = OutsourcedStaff::factory()->create(['employee_id' => '900001', 'name' => 'Registered staff', 'aadhaar_number' => '012345678901']);

        $this->actingAs($actor)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value, 'employee_number' => '012345678901',
            'staff_name' => 'Changed browser value', 'contractor_id' => $staff->contractor_id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_deficiency_memos', ['employee_number' => '900001', 'staff_name' => 'Registered staff']);
        $this->assertStringNotContainsString('012345678901', ServiceDeficiencyMemo::query()->sole()->toJson());
        $this->assertStringNotContainsString('012345678901', AuditLog::query()->get()->toJson());
    }

    public function test_unknown_employee_ids_allow_a_manually_entered_name(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->creator())->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value, 'employee_number' => 'UNKNOWN-1',
            'staff_name' => 'Manually entered staff', 'contractor_id' => $contractor->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_deficiency_memos', ['employee_number' => 'UNKNOWN-1', 'staff_name' => 'Manually entered staff']);
    }

    public function test_unknown_aadhaar_allows_a_manual_name_and_is_masked_in_the_saved_memo(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->creator())->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value, 'employee_number' => '012345678901',
            'staff_name' => 'Manually entered staff', 'contractor_id' => $contractor->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('service_deficiency_memos', ['employee_number' => 'XXXX XXXX 8901', 'staff_name' => 'Manually entered staff']);
        $this->assertStringNotContainsString('012345678901', AuditLog::query()->get()->toJson());
    }

    public function test_validation_failures_do_not_flash_aadhaar_into_the_session(): void
    {
        $this->actingAs($this->creator())->post(route('sdm-memos.store'), [
            'service_type' => 'invalid', 'employee_number' => '012345678901',
        ])->assertSessionHasErrors('service_type');

        $this->assertSame('XXXX XXXX 8901', session()->getOldInput('employee_number'));
        $this->assertStringNotContainsString('012345678901', json_encode(session()->getOldInput()));
    }

    public function test_replacing_or_clearing_aadhaar_updates_the_lookup_without_exposing_its_hash(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);
        $staff->update(['aadhaar_number' => '112345678901']);
        $this->assertArrayNotHasKey('aadhaar_lookup_hash', $staff->toArray());
        $this->actingAs($this->creator());
        $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => '012345678901', 'contractor_id' => $staff->contractor_id])
            ->assertExactJson(['status' => 'missing']);
        $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => '112345678901', 'contractor_id' => $staff->contractor_id])
            ->assertJsonPath('status', 'found');
        $staff->update(['aadhaar_number' => null]);
        $this->postJson(route('sdm-memos.staff-lookup'), ['staff_identifier' => '112345678901', 'contractor_id' => $staff->contractor_id])
            ->assertExactJson(['status' => 'missing']);
    }

    public function test_ambiguous_aadhaar_cannot_be_saved_as_an_arbitrary_employee_and_is_not_flashed(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);
        OutsourcedStaff::factory()->for($staff->contractor)->create(['aadhaar_number' => '012345678901']);

        $this->actingAs($this->creator())->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value, 'employee_number' => '012345678901',
            'staff_name' => 'Arbitrary name', 'contractor_id' => $staff->contractor_id,
        ])->assertSessionHasErrors('employee_number');

        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->assertSame('XXXX XXXX 8901', session()->getOldInput('employee_number'));
    }

    public function test_the_index_migration_backfills_existing_encrypted_aadhaar_and_can_be_reversed(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);
        $encrypted = $staff->getRawOriginal('aadhaar_number');
        $migration = require database_path('migrations/2026_11_10_120348_add_aadhaar_lookup_hash_to_outsourced_staff_table.php');
        $migration->down();
        $migration->up();

        $this->actingAs($this->creator())->postJson(route('sdm-memos.staff-lookup'), [
            'staff_identifier' => '012345678901', 'contractor_id' => $staff->contractor_id,
        ])->assertOk()->assertJsonPath('employee_number', $staff->employee_id);

        $this->assertSame($encrypted, $staff->fresh()->getRawOriginal('aadhaar_number'));
        $this->assertSame(64, strlen(DB::table('outsourced_staff')->where('id', $staff->id)->value('aadhaar_lookup_hash')));
    }

    public function test_an_authorized_draft_editor_can_lookup_staff_without_permission_to_create_new_memos(): void
    {
        $actor = $this->creator();
        $staff = OutsourcedStaff::factory()->create();
        $this->actingAs($actor)->post(route('sdm-memos.store'), ['service_type' => SdmType::Ticketing->value])->assertRedirect();
        $memo = ServiceDeficiencyMemo::query()->sole();
        $actor->roles()->firstOrFail()->revokePermissionTo(UserPermission::CreateMemos->value);

        $this->actingAs($actor->fresh())->postJson(route('sdm-memos.staff-lookup-for-edit', $memo), [
            'staff_identifier' => $staff->employee_id, 'contractor_id' => $staff->contractor_id,
        ])->assertOk()->assertJsonPath('staff_name', $staff->name);

        $this->actingAs(User::factory()->create())->postJson(route('sdm-memos.staff-lookup-for-edit', $memo), [
            'staff_identifier' => $staff->employee_id, 'contractor_id' => $staff->contractor_id,
        ])->assertForbidden();
    }

    private function creator(): User
    {
        $line = Line::query()->create(['code' => 'LOOKUP', 'name' => 'Lookup line', 'is_active' => true]);
        $station = Station::query()->create(['line_id' => $line->id, 'code' => 'LOOKUP-1', 'name' => 'Lookup station', 'is_active' => true]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $user->assignRole(UserRole::Station->value);

        return $user;
    }
}
