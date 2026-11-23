<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\OutsourcedStaff;
use Database\Seeders\OutsourcedStaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Tests\TestCase;

class OutsourcedStaffSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_private_snapshot_with_target_contractor_ids_encryption_and_safe_audits(): void
    {
        Contractor::factory()->create();
        $contractor = Contractor::factory()->create(['name' => 'Seed agency', 'is_active' => false]);
        $this->snapshot();

        $this->seed(OutsourcedStaffSeeder::class);

        $staff = OutsourcedStaff::query()->sole();
        $this->assertSame($contractor->id, $staff->contractor_id);
        $this->assertSame('000123_A', $staff->employee_id);
        $this->assertSame('Snapshot employee', $staff->name);
        $this->assertSame('Snapshot parent', $staff->father_name);
        $this->assertSame('1990-08-25', $staff->date_of_birth->toDateString());
        $this->assertSame('012345678901', $staff->aadhaar_number);
        $this->assertSame('00123/P', $staff->entry_permit_number);
        $this->assertFalse($contractor->fresh()->is_active);
        $this->assertNotSame('012345678901', DB::table('outsourced_staff')->value('aadhaar_number'));
        $this->assertSame(hash_hmac('sha256', 'outsourced-staff-aadhaar:012345678901', config('app.key')), $staff->aadhaar_lookup_hash);
        $audit = AuditLog::query()->sole();
        $this->assertNull($audit->actor_id);
        $this->assertSame('outsourced_staff.seeded', $audit->event);
        $this->assertSame($staff->id, $audit->auditable_id);
        $this->assertSame([
            'employee_id' => '000123_A',
            'designation' => 'TOM Operator', 'verification_status' => 'YES', 'is_active' => true,
            'contractor_id' => $contractor->id,
        ], $audit->after_values);
        $this->assertNull($audit->before_values);
    }

    public function test_creates_missing_contractors_and_leaves_existing_staff_and_audits_unchanged_on_reruns(): void
    {
        $this->snapshot();
        $this->seed(OutsourcedStaffSeeder::class);
        $staff = OutsourcedStaff::query()->sole();
        $staff->update(['employee_id' => '000123_a', 'name' => 'Maintained name', 'aadhaar_number' => null, 'is_active' => false]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'contractor.created', 'actor_id' => null]);

        $this->seed(OutsourcedStaffSeeder::class);

        $this->assertDatabaseCount('outsourced_staff', 1);
        $this->assertDatabaseCount('contractors', 1);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertSame('Maintained name', $staff->fresh()->name);
        $this->assertFalse($staff->fresh()->is_active);
        $this->assertNull($staff->fresh()->aadhaar_number);
    }

    public function test_invalid_staff_rolls_back_the_entire_snapshot(): void
    {
        $this->snapshot(['employee_id' => 'SECOND', 'aadhaar_number' => 'invalid']);

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Invalid snapshot must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('aadhaar_number', $exception->errors());
        }

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseCount('contractors', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_preserves_blank_identity_fields_and_inactive_status_without_accepting_source_database_ids(): void
    {
        $this->snapshot([
            'employee_id' => 'SECOND', 'aadhaar_number' => null, 'father_name' => null,
            'date_of_birth' => null, 'is_active' => false, 'contractor_id' => 9999,
            'aadhaar_lookup_hash' => 'untrusted source hash',
        ]);

        $this->seed(OutsourcedStaffSeeder::class);

        $staff = OutsourcedStaff::query()->where('employee_id', 'SECOND')->sole();
        $this->assertFalse($staff->is_active);
        $this->assertNull($staff->aadhaar_number);
        $this->assertNull($staff->aadhaar_lookup_hash);
        $this->assertNull($staff->father_name);
        $this->assertNull($staff->date_of_birth);
        $this->assertSame(Contractor::query()->sole()->id, $staff->contractor_id);
        $this->assertDatabaseCount('outsourced_staff', 2);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_unknown_contractor_rejects_snapshot_without_partial_records(): void
    {
        $this->snapshot(['employee_id' => 'SECOND', 'contractor_name' => 'Unknown agency']);

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Unknown contractor must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contractor_name', $exception->errors());
        }

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseCount('contractors', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_duplicate_employee_ids_in_snapshot_are_rejected_case_insensitively(): void
    {
        $this->snapshot(['employee_id' => '000123_a']);

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Duplicate source employee IDs must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_id', $exception->errors());
        }

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseCount('contractors', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_staff_and_new_contractors(): void
    {
        $this->snapshot();
        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->event === 'outsourced_staff.seeded') {
                throw new RuntimeException('Audit unavailable.');
            }
        });

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Seeding must fail when its audit cannot be saved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable.', $exception->getMessage());
        }

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseCount('contractors', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_private_snapshot_fails_with_deployment_instructions(): void
    {
        Storage::fake('local');

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Missing private snapshot must be reported.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('seed-data/outsourced-staff.json', $exception->getMessage());
        }

        $this->assertDatabaseCount('outsourced_staff', 0);
    }

    public function test_malformed_private_snapshot_fails_before_writing(): void
    {
        Storage::fake('local')->put('seed-data/outsourced-staff.json', '{invalid');

        try {
            $this->seed(OutsourcedStaffSeeder::class);
            $this->fail('Malformed snapshot must be rejected.');
        } catch (JsonException) {
            $this->assertDatabaseCount('outsourced_staff', 0);
            $this->assertDatabaseCount('contractors', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    private function snapshot(?array $second = null): void
    {
        $row = [
            'contractor_name' => 'Seed agency', 'employee_id' => '000123_A', 'name' => 'Snapshot employee',
            'father_name' => 'Snapshot parent', 'date_of_birth' => '1990-08-25', 'aadhaar_number' => '012345678901',
            'category' => 'GEN', 'gender' => 'MALE', 'designation' => 'TOM Operator',
            'entry_permit_number' => '00123/P', 'verification_status' => 'YES', 'is_active' => true,
        ];
        Storage::fake('local')->put('seed-data/outsourced-staff.json', json_encode([
            'contractors' => [['name' => 'Seed agency', 'is_active' => true]],
            'staff' => $second === null ? [$row] : [$row, array_replace($row, $second)],
        ], JSON_THROW_ON_ERROR));
    }
}
