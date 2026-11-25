<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\HistoricalSdm;
use App\Models\Line;
use App\Models\OutsourcedStaff;
use App\Models\Station;
use App\Models\User;
use App\Services\HistoricalSdmImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use ZipArchive;

class HistoricalSdmImportTest extends TestCase
{
    use RefreshDatabase;

    private array $workbooks = [];

    protected function tearDown(): void
    {
        foreach ($this->workbooks as $path) {
            unlink($path);
        }
        parent::tearDown();
    }

    public function test_import_preserves_source_values_and_links_existing_masters_without_creating_live_memos(): void
    {
        $agency = Contractor::factory()->create();
        $actor = User::factory()->create();
        $staff = OutsourcedStaff::factory()->for($agency)->create(['employee_id' => '009001', 'aadhaar_number' => '012345678901']);
        $line = Line::query()->create(['code' => '8', 'name' => 'Magenta']);
        $station = Station::query()->create(['code' => 'IGDA', 'name' => 'IGI Domestic Airport', 'line_id' => $line->id, 'line_code' => '8']);

        $result = app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, $actor);

        $this->assertSame(1, $result['created']);
        $record = HistoricalSdm::query()->sole();
        $this->assertSame('2025-01-01', $record->incident_date->toDateString());
        $this->assertSame('009001', $record->employee_number);
        $this->assertSame('012345678901', $record->aadhaar_number);
        $this->assertNotSame('012345678901', $record->getRawOriginal('aadhaar_number'));
        $this->assertArrayNotHasKey('aadhaar_number', $record->toArray());
        $this->assertSame($staff->id, $record->outsourcedStaff->id);
        $this->assertSame($station->id, $record->station->id);
        $this->assertSame($agency->id, $record->contractor->id);
        $this->assertSame('AM/Ops/L-8E', $record->memo_number);
        $this->assertSame('Source employee', $record->employee_name);
        $this->assertSame('Cash shortage', $record->discrepancies);
        $this->assertSame('Warning', $record->recommended_action);
        $this->assertSame('1667.00', $record->contractor_penalty);
        $this->assertSame('JMD.1', $record->cp_number);
        $this->assertSame('5.00', $record->negative_points);
        $this->assertSame([], $record->import_issues);
        $this->assertNotNull($record->created_at);
        $this->assertSame($actor->id, $record->import->imported_by);
        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $audit = AuditLog::query()->where('event', 'historical_sdm.imported')->sole();
        $this->assertSame($actor->id, $audit->actor_id);
        $this->assertSame($record->import_id, $audit->auditable_id);
        $this->assertStringNotContainsString('012345678901', json_encode([$audit->before_values, $audit->after_values]));
    }

    public function test_uncertain_source_values_are_preserved_and_flagged_without_inventing_master_records(): void
    {
        $agency = Contractor::factory()->create();
        $row = $this->row();
        $row[2] = '29107-2026';
        $row[5] = 'UNKNOWN';
        $row[7] = '';
        $row[8] = 'Not found';
        $row[9] = '';
        $row[15] = '';

        app(HistoricalSdmImportService::class)->import($this->workbook([$row]), $agency->id, User::factory()->create());

        $record = HistoricalSdm::query()->sole();
        $this->assertNull($record->incident_date);
        $this->assertSame('29107-2026', $record->source_date);
        $this->assertNull($record->station_id);
        $this->assertNull($record->outsourced_staff_id);
        $this->assertSame('Not found', $record->aadhaar_number);
        $this->assertNull($record->negative_points);
        $this->assertNull($record->discrepancies);
        $this->assertContains('invalid_date', $record->import_issues);
        $this->assertContains('invalid_aadhaar', $record->import_issues);
        $this->assertContains('station_unmatched', $record->import_issues);
        $this->assertContains('staff_unmatched', $record->import_issues);
        $this->assertDatabaseCount('outsourced_staff', 0);
        $this->assertDatabaseCount('stations', 0);
    }

    public function test_nces_history_uses_the_worksheet_columns_after_the_repeated_aadhaar_heading(): void
    {
        $agency = Contractor::factory()->create();
        $sheet = 'RECORD(NCES.79)';
        $headers = ['Unique S No', 'S No', 'Date', 'Aadhar Number', 'Memo No/Checked from', 'Line', 'Station', 'Employee Name', 'File No', 'Aadhar Number', 'Discrepancies', 'Recommended Action', 'Penalty on Contractor(in Rs)', 'SDM S No', 'C&P', 'C&P', 'Negative points'];
        $row = ['42', '1', '45658', '012345678901', 'NCES-MEMO-1', '7E', 'TEST', 'Source employee', 'NC001', '012345678901', 'Cash shortage', 'Warning', '750', '1', 'NCES.79', 'SOP', '3'];
        $path = $this->workbook([$row], $headers, $sheet);

        $result = app(HistoricalSdmImportService::class)->import($path, $agency->id, null, sheet: $sheet);

        $this->assertSame(1, $result['created']);
        $record = HistoricalSdm::query()->sole();
        $this->assertSame($sheet, $record->import->source_sheet);
        $this->assertSame(2, $record->source_row);
        $this->assertSame('42', $record->source_usn);
        $this->assertSame('2025-01-01', $record->incident_date->toDateString());
        $this->assertSame('NCES-MEMO-1', $record->memo_number);
        $this->assertSame('7E', $record->source_line);
        $this->assertSame('TEST', $record->source_station);
        $this->assertSame('Source employee', $record->employee_name);
        $this->assertSame('NC001', $record->employee_number);
        $this->assertSame('012345678901', $record->aadhaar_number);
        $this->assertSame('Cash shortage', $record->discrepancies);
        $this->assertSame('Warning', $record->recommended_action);
        $this->assertSame('750.00', $record->contractor_penalty);
        $this->assertSame('NCES.79', $record->cp_number);
        $this->assertSame('3.00', $record->negative_points);
    }

    public function test_reimport_skips_the_same_workbook_but_keeps_distinct_rows_with_repeated_memo_numbers(): void
    {
        $agency = Contractor::factory()->create();
        $actor = User::factory()->create();
        $path = $this->workbook([$this->row(), $this->row()]);
        $service = app(HistoricalSdmImportService::class);
        $service->import($path, $agency->id, $actor);

        $result = $service->import($path, $agency->id, $actor);

        $this->assertSame(0, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertDatabaseCount('historical_sdms', 2);
        $this->assertDatabaseCount('historical_sdm_imports', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_dry_run_validates_and_reports_without_writes(): void
    {
        $agency = Contractor::factory()->create();

        $result = app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, User::factory()->create(), true);

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_invalid_penalty_aborts_all_records_and_audits(): void
    {
        $agency = Contractor::factory()->create();
        $row = $this->row();
        $row[11] = 'not money';

        try {
            app(HistoricalSdmImportService::class)->import($this->workbook([$this->row(), $row]), $agency->id, User::factory()->create());
            $this->fail('Invalid financial values must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Row 3', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_conflicting_staff_identity_and_ambiguous_station_are_left_unlinked(): void
    {
        $agency = Contractor::factory()->create();
        OutsourcedStaff::factory()->for($agency)->create(['employee_id' => '009001', 'aadhaar_number' => '999999999999']);
        Station::query()->create(['code' => 'IGDA', 'station_code' => 'IGDA', 'name' => 'First', 'line_code' => '8']);
        Station::query()->create(['code' => 'IGDA-8', 'station_code' => 'IGDA', 'name' => 'Second', 'line_code' => '8']);

        app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, User::factory()->create());

        $record = HistoricalSdm::query()->sole();
        $this->assertNull($record->outsourced_staff_id);
        $this->assertNull($record->station_id);
        $this->assertContains('staff_identity_conflict', $record->import_issues);
        $this->assertContains('station_ambiguous', $record->import_issues);
    }

    public function test_database_failure_rolls_back_the_import_and_audit(): void
    {
        $agency = Contractor::factory()->create();
        DB::statement("CREATE TRIGGER reject_historical_sdm BEFORE INSERT ON historical_sdms BEGIN SELECT RAISE(ABORT, 'test failure'); END");

        try {
            app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, User::factory()->create());
            $this->fail('The database trigger must reject the insert.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('test failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_command_requires_an_enabled_authorized_actor(): void
    {
        $agency = Contractor::factory()->create();
        $this->artisan('sdm:import-history', ['file' => $this->workbook([$this->row()]), '--contractor' => $agency->id, '--actor' => User::factory()->create()->id])->assertFailed();
        $this->assertDatabaseCount('historical_sdms', 0);
    }

    public function test_shared_strings_and_cached_formula_values_are_read_from_the_selected_sheet(): void
    {
        $agency = Contractor::factory()->create();
        $path = $this->workbook([$this->row()]);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = str_replace('<c r="F2" t="inlineStr"><is><t>IGDA</t></is></c>', '<c r="F2" t="s"><v>0</v></c>', $xml);
        $xml = str_replace('<c r="I2" t="inlineStr"><is><t>012345678901</t></is></c>', '<c r="I2" t="str"><f>AUTH!I3</f><v>012345678901</v></c>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->addFromString('xl/sharedStrings.xml', '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>IGDA</t></si></sst>');
        $zip->close();

        app(HistoricalSdmImportService::class)->import($path, $agency->id, User::factory()->create());

        $record = HistoricalSdm::query()->sole();
        $this->assertSame('IGDA', $record->source_station);
        $this->assertSame('012345678901', $record->aadhaar_number);
    }

    public function test_wrong_worksheet_fails_before_any_writes(): void
    {
        $agency = Contractor::factory()->create();
        $path = $this->workbook([$this->row()]);

        try {
            app(HistoricalSdmImportService::class)->import($path, $agency->id, User::factory()->create(), false, 'AUTH');
            $this->fail('The unrelated staff reference sheet must not be imported.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('worksheet was not found', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_missing_penalty_heading_fails_before_any_writes(): void
    {
        $agency = Contractor::factory()->create();
        $path = $this->workbook([$this->row()]);
        $zip = new ZipArchive;
        $zip->open($path);
        $xml = str_replace('PENALTY ON M/s JMD (in ₹)', 'UNKNOWN AMOUNT', $zip->getFromName('xl/worksheets/sheet1.xml'));
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        try {
            app(HistoricalSdmImportService::class)->import($path, $agency->id, User::factory()->create());
            $this->fail('An unrecognized penalty column must not be imported.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('penalty heading is missing', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_failed_audit_rolls_back_all_bulk_inserted_history(): void
    {
        $agency = Contractor::factory()->create();
        DB::statement("CREATE TRIGGER reject_history_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        try {
            app(HistoricalSdmImportService::class)->import($this->workbook([$this->row(), $this->row()]), $agency->id, User::factory()->create());
            $this->fail('The audit must be atomic with the imported records.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_history_migration_can_be_rolled_back_and_reapplied_without_touching_masters(): void
    {
        $agency = Contractor::factory()->create();
        $migration = require database_path('migrations/2026_11_18_123104_create_historical_sdm_tables.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('historical_sdms'));
        $this->assertFalse(Schema::hasTable('historical_sdm_imports'));
        $this->assertModelExists($agency);
        $migration->up();
        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
    }

    public function test_history_foreign_keys_prevent_deleting_linked_master_records(): void
    {
        $agency = Contractor::factory()->create();
        app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, User::factory()->create());

        try {
            $agency->delete();
            $this->fail('Historical agency links must be retained.');
        } catch (QueryException) {
            $this->assertModelExists($agency);
        }

        $this->assertDatabaseCount('historical_sdms', 1);
    }

    public function test_deleting_a_station_used_by_history_returns_validation_feedback(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Administrator);
        $agency = Contractor::factory()->create();
        $station = Station::query()->create(['code' => 'IGDA', 'name' => 'Airport', 'line_code' => '8']);
        app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, $actor);

        $this->actingAs($actor)->delete(route('stations.destroy', $station))->assertSessionHasErrors('station');

        $this->assertModelExists($station);
        $this->assertDatabaseCount('historical_sdms', 1);
    }

    public function test_deleting_a_contractor_used_by_history_returns_validation_feedback(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Administrator);
        $agency = Contractor::factory()->create();
        app(HistoricalSdmImportService::class)->import($this->workbook([$this->row()]), $agency->id, $actor);

        $this->actingAs($actor)->delete(route('contractors.destroy', $agency))->assertSessionHasErrors('contractor');

        $this->assertModelExists($agency);
        $this->assertDatabaseCount('historical_sdms', 1);
    }

    private function row(): array
    {
        return ['1', '1', '45658', 'AM/Ops/L-8E', '8 E', 'IGDA', 'Source employee', '009001', '012345678901', 'Cash shortage', 'Warning', '1667', '1', 'JMD.1', '', '5'];
    }

    private function workbook(array $rows, ?array $headers = null, string $sheet = 'previous records of JMD'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sdm-history-');
        $this->workbooks[] = $path;
        $headers ??= ['USN', 'SNO', 'DATE', 'MEMO NO', 'LINE', 'STATION', 'ON DUTY STAFF', 'FILE NO', 'Aadhar Number', 'BRIEF DETAIL OF VIOLATION', 'RECOMMENDED ACTIONS', 'PENALTY ON M/s JMD (in ₹)', 'Sr. No.', 'C&P No', '', 'Negative Points'];
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers, ...$rows] as $index => $row) {
            $number = $index + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($row as $column => $value) {
                $ref = chr(65 + $column).$number;
                $xml .= '<c r="'.$ref.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.htmlspecialchars($sheet, ENT_XML1).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        return $path;
    }
}
