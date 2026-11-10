<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\OutsourcedStaff;
use App\Models\User;
use App\Services\OutsourcedStaffImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class OutsourcedStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_can_create_staff_with_one_dob_and_encrypted_aadhaar(): void
    {
        $actor = $this->administrator();
        $contractor = Contractor::factory()->create();

        $this->actingAs($actor)->post(route('outsourced-staff.store'), $this->payload($contractor))
            ->assertRedirect(route('outsourced-staff.index'))->assertSessionHasNoErrors();

        $staff = OutsourcedStaff::query()->sole();
        $this->assertSame('1990-08-25', $staff->date_of_birth->toDateString());
        $this->assertSame('012345678901', $staff->aadhaar_number);
        $this->assertNotSame('012345678901', $staff->getRawOriginal('aadhaar_number'));
        $this->assertArrayNotHasKey('aadhaar_number', $staff->toArray());
        $this->assertSame($contractor->id, $staff->contractor->id);
        $this->assertSame('00123D', $staff->entry_permit_number);
        $audit = AuditLog::query()->where('event', 'outsourced_staff.created')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame($staff->id, $audit->auditable_id);
        $this->assertStringNotContainsString('012345678901', json_encode($audit->after_values));
        $this->assertArrayNotHasKey('date_of_birth', $audit->after_values);
    }

    public function test_edit_masks_aadhaar_and_blank_input_preserves_it_while_other_details_can_change(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);
        $contractor = Contractor::factory()->create();
        $this->actingAs($this->administrator())->get(route('outsourced-staff.edit', $staff))
            ->assertOk()->assertSee('XXXX XXXX 8901')->assertDontSee('012345678901', false);

        $this->put(route('outsourced-staff.update', $staff), [
            ...$this->payload($contractor), 'employee_id' => $staff->employee_id,
            'name' => 'Updated employee', 'aadhaar_number' => '', 'is_active' => '0',
        ])->assertRedirect(route('outsourced-staff.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('outsourced_staff', ['id' => $staff->id, 'name' => 'Updated employee', 'contractor_id' => $contractor->id, 'is_active' => false]);
        $this->assertSame('012345678901', $staff->fresh()->aadhaar_number);
        $audit = AuditLog::query()->where('event', 'outsourced_staff.updated')->sole();
        $this->assertTrue($audit->before_values['is_active']);
        $this->assertFalse($audit->after_values['is_active']);
    }

    public function test_aadhaar_can_be_explicitly_cleared(): void
    {
        $staff = OutsourcedStaff::factory()->create(['aadhaar_number' => '012345678901']);

        $this->actingAs($this->administrator())->put(route('outsourced-staff.update', $staff), [
            ...$this->payload($staff->contractor), 'employee_id' => $staff->employee_id,
            'aadhaar_number' => '', 'clear_aadhaar' => '1',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($staff->fresh()->aadhaar_number);
    }

    #[DataProvider('invalidStaff')]
    public function test_invalid_staff_data_is_rejected_without_writes(string $field, mixed $value): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->administrator())->post(route('outsourced-staff.store'), [
            ...$this->payload($contractor), $field => $value,
        ])->assertSessionHasErrors($field);

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'outsourced_staff.created']);
        $this->assertNull(session()->getOldInput('aadhaar_number'));
    }

    public static function invalidStaff(): array
    {
        return [
            'missing employee ID' => ['employee_id', ''],
            'missing name' => ['name', ''],
            'missing contractor' => ['contractor_id', null],
            'unknown contractor' => ['contractor_id', 99999],
            'impossible DOB' => ['date_of_birth', '1990-02-30'],
            'future DOB' => ['date_of_birth', '2999-01-01'],
            'malformed Aadhaar' => ['aadhaar_number', '12345678901X'],
            'short Aadhaar' => ['aadhaar_number', '12345678901'],
            'invalid status' => ['is_active', 'yes'],
        ];
    }

    public function test_duplicate_employee_id_is_rejected(): void
    {
        $staff = OutsourcedStaff::factory()->create(['employee_id' => '900001']);

        $this->actingAs($this->administrator())->post(route('outsourced-staff.store'), $this->payload($staff->contractor))
            ->assertSessionHasErrors(['employee_id' => 'The employee id has already been taken.']);

        $this->assertDatabaseCount('outsourced_staff', 1);
    }

    public function test_staff_list_filters_by_agency_status_verification_and_search_and_escapes_names(): void
    {
        $contractor = Contractor::factory()->create();
        $staff = OutsourcedStaff::factory()->for($contractor)->create([
            'name' => '<script>Selected employee</script>', 'verification_status' => 'SLIP', 'is_active' => false,
        ]);
        OutsourcedStaff::factory()->for($contractor)->create(['name' => 'Selected active employee', 'verification_status' => 'SLIP']);
        OutsourcedStaff::factory()->create(['name' => 'Selected other agency', 'verification_status' => 'SLIP', 'is_active' => false]);

        $this->actingAs($this->administrator())->get(route('outsourced-staff.index', [
            'q' => 'Selected', 'contractor_id' => $contractor->id, 'is_active' => '0', 'verification_status' => 'SLIP',
        ]))->assertOk()->assertSee($staff->name)->assertDontSee($staff->name, false)
            ->assertDontSee('Selected active employee')->assertDontSee('Selected other agency')
            ->assertViewHas('staff', fn ($rows) => $rows->total() === 1);
    }

    public function test_staff_list_is_paginated_and_does_not_expose_aadhaar(): void
    {
        OutsourcedStaff::factory()->count(26)->create(['aadhaar_number' => '012345678901']);

        $this->actingAs($this->administrator())->get(route('outsourced-staff.index'))
            ->assertOk()->assertDontSee('012345678901', false)
            ->assertViewHas('staff', fn ($rows) => $rows->total() === 26 && $rows->count() === 25);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/outsourced-staff')->assertRedirect(route('login'));
    }

    public function test_unprivileged_users_cannot_read_write_or_import_staff(): void
    {
        $staff = OutsourcedStaff::factory()->create();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('outsourced-staff.index'))->assertForbidden();
        $this->get(route('outsourced-staff.create'))->assertForbidden();
        $this->get(route('outsourced-staff.edit', $staff))->assertForbidden();
        $this->post(route('outsourced-staff.store'), $this->payload($staff->contractor))->assertForbidden();
        $this->put(route('outsourced-staff.update', $staff), $this->payload($staff->contractor))->assertForbidden();
        $this->post(route('outsourced-staff.import'), [])->assertForbidden();
        $this->assertDatabaseCount('outsourced_staff', 1);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'outsourced_staff.created']);
    }

    public function test_contractors_with_staff_cannot_be_deleted(): void
    {
        $staff = OutsourcedStaff::factory()->create();

        $this->actingAs($this->administrator())->delete(route('contractors.destroy', $staff->contractor))
            ->assertSessionHasErrors('contractor');

        $this->assertModelExists($staff->contractor);
        $this->assertModelExists($staff);
    }

    public function test_import_combines_dob_preserves_source_labels_and_leading_zeroes_and_reports_bad_aadhaar(): void
    {
        $contractor = Contractor::factory()->create();
        $actor = $this->administrator();

        $this->actingAs($actor)->post(route('outsourced-staff.import'), [
            'file' => $this->workbook([
                $this->sourceRow(),
                [...$this->sourceRow(), 0 => '900002', 6 => '12345678901', 8 => 'MAIE', 11 => 'UNDER PROCESS'],
            ]),
            'agency_map' => ['JMD' => $contractor->id],
            'invalid_aadhaar' => 'blank',
        ])->assertRedirect(route('outsourced-staff.index'))->assertSessionHasNoErrors()
            ->assertSessionHas('import_result.created', 2)
            ->assertSessionHas('import_result.warnings', ['Row 3 (employee 900002): invalid Aadhaar left blank.']);

        $first = OutsourcedStaff::query()->where('employee_id', '900001')->firstOrFail();
        $this->assertSame('1990-08-25', $first->date_of_birth->toDateString());
        $this->assertSame('012345678901', $first->aadhaar_number);
        $this->assertSame('00123D', $first->entry_permit_number);
        $second = OutsourcedStaff::query()->where('employee_id', '900002')->firstOrFail();
        $this->assertNull($second->aadhaar_number);
        $this->assertSame('MAIE', $second->gender);
        $this->assertSame('UNDER PROCESS', $second->verification_status);
        $this->assertSame($contractor->id, $second->contractor_id);
        $this->assertSame(2, AuditLog::query()->where('event', 'outsourced_staff.created')->where('actor_id', $actor->id)->count());
    }

    public function test_reimport_skips_existing_employee_ids_and_preserves_maintained_details(): void
    {
        $staff = OutsourcedStaff::factory()->create(['employee_id' => '900001', 'name' => 'Maintained name', 'is_active' => false]);

        $this->actingAs($this->administrator())->post(route('outsourced-staff.import'), [
            'file' => $this->workbook([$this->sourceRow()]), 'agency_map' => ['JMD' => $staff->contractor_id],
            'invalid_aadhaar' => 'blank',
        ])->assertSessionHasNoErrors()->assertSessionHas('import_result.skipped', 1)->assertSessionHas('import_result.created', 0);

        $this->assertDatabaseCount('outsourced_staff', 1);
        $this->assertSame('Maintained name', $staff->fresh()->name);
        $this->assertFalse($staff->fresh()->is_active);
    }

    #[DataProvider('invalidImports')]
    public function test_invalid_imports_do_not_partially_write(array $row, string $message): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->administrator())->post(route('outsourced-staff.import'), [
            'file' => $this->workbook([$this->sourceRow(), $row]),
            'agency_map' => ['JMD' => $contractor->id], 'invalid_aadhaar' => 'blank',
        ])->assertSessionHasErrors('file')->assertSessionHasErrors(['file' => $message]);

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'outsourced_staff.created']);
    }

    public static function invalidImports(): array
    {
        $row = ['900002', 'Test employee', 'Test father', '25', '08', '1990', '012345678901', '', 'MALE', 'TOM Operator', '00123D', 'YES', 'JMD'];

        return [
            'impossible DOB' => [[...$row, 3 => '31', 4 => '02'], 'Row 3: invalid date of birth.'],
            'unknown agency' => [[...$row, 12 => 'UNKNOWN'], 'Row 3: map contractor agency UNKNOWN before importing.'],
            'duplicate ID' => [[...$row, 0 => '900001'], 'Row 3: duplicate employee ID 900001 in the workbook.'],
        ];
    }

    public function test_import_rejects_a_workbook_with_the_wrong_headers(): void
    {
        $this->actingAs($this->administrator())->post(route('outsourced-staff.import'), [
            'file' => $this->workbook([['Wrong header']], false), 'invalid_aadhaar' => 'blank',
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('outsourced_staff', 0);
    }

    public function test_import_can_stop_on_malformed_aadhaar_without_creating_any_staff(): void
    {
        $contractor = Contractor::factory()->create();

        $this->actingAs($this->administrator())->post(route('outsourced-staff.import'), [
            'file' => $this->workbook([$this->sourceRow(), [...$this->sourceRow(), 0 => '900002', 6 => '123']]),
            'agency_map' => ['JMD' => $contractor->id], 'invalid_aadhaar' => 'reject',
        ])->assertSessionHasErrors(['file' => 'Row 3: invalid Aadhaar. Correct it or choose to leave invalid Aadhaar blank.']);

        $this->assertDatabaseCount('outsourced_staff', 0);
    }

    public function test_import_command_dry_run_validates_without_creating_staff_or_audit_records(): void
    {
        $contractor = Contractor::factory()->create();
        $actor = $this->administrator();
        $file = $this->workbook([$this->sourceRow()]);

        $this->artisan('outsourced-staff:import', [
            'file' => $file->getRealPath(), '--actor' => $actor->id,
            '--agency' => ['JMD='.$contractor->id], '--dry-run' => true,
        ])->expectsOutput('Dry run: 1 new staff; 0 existing employee IDs skipped.')->assertSuccessful();

        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'outsourced_staff.created']);
    }

    public function test_import_command_requires_an_enabled_authorized_actor(): void
    {
        $actor = User::factory()->disabled()->create();
        $actor->assignRole(UserRole::Administrator->value);

        $this->artisan('outsourced-staff:import', ['file' => 'not-used.xlsx', '--actor' => $actor->id])
            ->expectsOutput('Specify an enabled --actor user with contractor management permission.')->assertFailed();

        $this->assertDatabaseCount('outsourced_staff', 0);
    }

    public function test_a_failed_audit_rolls_back_the_entire_import_including_previously_written_rows(): void
    {
        $actor = $this->administrator();
        $contractor = Contractor::factory()->create();
        $file = $this->workbook([$this->sourceRow(), [...$this->sourceRow(), 0 => '900002']]);
        $dispatcher = AuditLog::getEventDispatcher();
        AuditLog::setEventDispatcher(clone $dispatcher);
        $count = 0;
        AuditLog::creating(function (AuditLog $log) use (&$count): void {
            if ($log->event === 'outsourced_staff.created' && ++$count === 2) {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            app(OutsourcedStaffImportService::class)->import($file->getRealPath(), ['JMD' => $contractor->id], 'reject', $actor);
            $this->fail('Import should fail if the audit record cannot be saved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit storage unavailable.', $exception->getMessage());
        } finally {
            AuditLog::setEventDispatcher($dispatcher);
        }

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'outsourced_staff.created']);
    }

    public function test_staff_migration_can_be_reversed_and_reapplied_on_an_empty_database(): void
    {
        $migration = require database_path('migrations/2026_11_10_104609_create_outsourced_staff_table.php');
        $migration->down();
        $migration->up();

        $staff = OutsourcedStaff::factory()->create();
        $this->assertModelExists($staff);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);

        return $user;
    }

    private function payload(Contractor $contractor): array
    {
        return [
            'employee_id' => '900001', 'name' => 'Test employee', 'father_name' => 'Test father',
            'date_of_birth' => '1990-08-25', 'aadhaar_number' => '012345678901', 'category' => 'GEN',
            'gender' => 'MALE', 'designation' => 'TOM Operator', 'entry_permit_number' => '00123D',
            'verification_status' => 'YES', 'contractor_id' => $contractor->id, 'is_active' => '1',
        ];
    }

    private function sourceRow(): array
    {
        return ['900001', 'Test employee', 'Test father', '25', '08 ', '1990', '012345678901', '', 'MALE', 'TOM Operator', '00123D', 'YES', 'JMD'];
    }

    private function workbook(array $rows, bool $headers = true): UploadedFile
    {
        if ($headers) {
            array_unshift($rows, ['EMP ID', 'NAME OF EMPLOYEE', "FATHER'S NAME OF EMPLOYEE", 'DATE', 'MONTH', 'YEAR', 'ADHAR NUMBER OF EMPLOYEE', 'Category', 'GENDER OF EMPLOYEE', 'DESIGNATION OF EMPLOYEE', 'ENTRY PERMIT CARD NUMBER', 'Verification Status', 'Contractor Agency']);
        }
        $sheet = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $i => $row) {
            $sheet .= '<row r="'.($i + 1).'">';
            foreach ($row as $j => $value) {
                $sheet .= '<c r="'.chr(65 + $j).($i + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $path = tempnam(sys_get_temp_dir(), 'staff-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $file = UploadedFile::fake()->createWithContent('staff.xlsx', file_get_contents($path));
        unlink($path);

        return $file;
    }
}
