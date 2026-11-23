<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\HistoricalSdm;
use App\Models\Line;
use App\Models\OutsourcedStaff;
use App\Models\Station;
use App\Models\User;
use App\Services\HistoricalSdmImportService;
use Database\Seeders\HistoricalSdmSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class HistoricalSdmSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_private_workbook_with_server_master_ids_encryption_and_system_audit(): void
    {
        Contractor::factory()->create();
        $contractor = Contractor::factory()->create(['name' => 'Seed agency', 'is_active' => false]);
        $staff = OutsourcedStaff::factory()->for($contractor)->create(['employee_id' => '009001', 'aadhaar_number' => '012345678901']);
        $line = Line::query()->create(['code' => '8', 'name' => 'Magenta']);
        $station = Station::query()->create(['code' => 'IGDA', 'name' => 'Airport', 'line_id' => $line->id, 'line_code' => '8']);
        $this->manifest();

        $this->artisan('db:seed', ['--class' => HistoricalSdmSeeder::class, '--force' => true])
            ->expectsOutputToContain('2 created; 0 previously imported rows skipped')
            ->assertSuccessful();

        $records = HistoricalSdm::query()->orderBy('source_row')->get();
        $this->assertCount(2, $records);
        $record = $records->first();
        $this->assertSame($contractor->id, $record->contractor_id);
        $this->assertSame($staff->id, $record->outsourced_staff_id);
        $this->assertSame($station->id, $record->station_id);
        $this->assertSame([2, 3], $records->pluck('source_row')->all());
        $this->assertSame(['AM/Ops/L-8E', 'AM/Ops/L-8E'], $records->pluck('memo_number')->all());
        $this->assertSame('2025-01-01', $record->incident_date->toDateString());
        $this->assertSame('009001', $record->employee_number);
        $this->assertSame('Source employee', $record->employee_name);
        $this->assertSame('012345678901', $record->aadhaar_number);
        $this->assertNotSame('012345678901', $record->getRawOriginal('aadhaar_number'));
        $this->assertSame('1667.00', $record->contractor_penalty);
        $this->assertSame('5.00', $record->negative_points);
        $this->assertSame([], $record->import_issues);
        $this->assertFalse($contractor->fresh()->is_active);
        $this->assertNull($record->import->imported_by);
        $this->assertSame('history.xlsx', $record->import->source_file);
        $this->assertSame(hash_file('sha256', Storage::disk('local')->path('seed-data/history.xlsx')), $record->import->source_sha256);
        $this->assertSame(2, $record->import->row_count);
        $audit = AuditLog::query()->sole();
        $this->assertNull($audit->actor_id);
        $this->assertSame('historical_sdm.imported', $audit->event);
        $this->assertSame($record->import_id, $audit->auditable_id);
        $this->assertSame(['contractor_id', 'source_sha256', 'source_sheet', 'row_count', 'issues'], array_keys($audit->after_values));
        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('outsourced_staff', 1);
        $this->assertDatabaseCount('stations', 1);
    }

    public function test_reruns_preserve_an_existing_import_and_its_actor_without_duplicate_rows_or_audits(): void
    {
        $contractor = Contractor::factory()->create(['name' => 'SEED AGENCY']);
        $actor = User::factory()->create();
        $this->manifest();
        app(HistoricalSdmImportService::class)->import(Storage::disk('local')->path('seed-data/history.xlsx'), $contractor->id, $actor);
        $record = HistoricalSdm::query()->first();
        $record->update(['recommended_action' => 'Maintained server value']);

        $this->seed(HistoricalSdmSeeder::class);
        $this->seed(HistoricalSdmSeeder::class);

        $this->assertDatabaseCount('historical_sdms', 2);
        $this->assertDatabaseCount('historical_sdm_imports', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertSame($actor->id, $record->import->imported_by);
        $this->assertSame('Maintained server value', $record->fresh()->recommended_action);
    }

    public function test_missing_manifest_explains_which_private_file_to_upload(): void
    {
        Storage::fake('local');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('storage/app/private/seed-data/historical-sdms.json');

        $this->seed(HistoricalSdmSeeder::class);
    }

    public function test_malformed_manifest_does_not_write_history(): void
    {
        Storage::fake('local')->put('seed-data/historical-sdms.json', '{invalid');

        try {
            $this->seed(HistoricalSdmSeeder::class);
            $this->fail('Malformed JSON must be rejected.');
        } catch (JsonException) {
            $this->assertDatabaseCount('historical_sdm_imports', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    #[DataProvider('invalidEntries')]
    public function test_invalid_bundle_rolls_back_without_changing_master_data(array $changes, string $message): void
    {
        Contractor::factory()->create(['name' => 'Seed agency']);
        $this->manifest($changes);

        try {
            $this->seed(HistoricalSdmSeeder::class);
            $this->fail('An invalid historical bundle must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('contractors', 1);
    }

    public static function invalidEntries(): array
    {
        return [
            'unknown contractor' => [['contractor_name' => 'Unknown agency'], 'exactly one existing contractor'],
            'changed workbook' => [['sha256' => str_repeat('0', 64)], 'checksum'],
            'missing workbook' => [['file' => 'missing.xlsx'], 'Upload'],
            'parent path' => [['file' => '../history.xlsx'], 'file'],
            'windows path' => [['file' => '..\\history.xlsx'], 'file'],
            'wrong worksheet' => [['sheet' => 'AUTH'], 'worksheet was not found'],
            'wrong row count' => [['row_count' => 3], 'row count'],
        ];
    }

    #[DataProvider('invalidManifests')]
    public function test_invalid_manifest_structure_is_rejected_before_writes(mixed $manifest): void
    {
        Storage::fake('local')->put('seed-data/historical-sdms.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        try {
            $this->seed(HistoricalSdmSeeder::class);
            $this->fail('An unsupported or incomplete manifest must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('historical_sdms', 0);
            $this->assertDatabaseCount('historical_sdm_imports', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        }
    }

    public static function invalidManifests(): array
    {
        return [
            'null JSON' => [null],
            'unsupported version' => [['version' => 2, 'imports' => []]],
            'missing workbook provenance' => [['version' => 1, 'imports' => [['file' => 'history.xlsx']]]],
        ];
    }

    public function test_invalid_later_workbook_rolls_back_all_earlier_imports_and_audits(): void
    {
        Contractor::factory()->create(['name' => 'Seed agency']);
        $manifest = $this->manifest();
        $row = $this->row();
        $row[11] = 'not money';
        $path = Storage::disk('local')->path('seed-data/invalid.xlsx');
        $this->workbook($path, [$row]);
        $manifest['imports'][] = [...$manifest['imports'][0], 'file' => 'invalid.xlsx', 'sha256' => hash_file('sha256', $path), 'row_count' => 1];
        Storage::disk('local')->put('seed-data/historical-sdms.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        try {
            $this->seed(HistoricalSdmSeeder::class);
            $this->fail('Invalid financial values must reject the complete bundle.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Row 2', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_rolls_back_seeded_history(): void
    {
        Contractor::factory()->create(['name' => 'Seed agency']);
        $this->manifest();
        DB::statement("CREATE TRIGGER reject_seed_audit BEFORE INSERT ON audit_logs BEGIN SELECT RAISE(ABORT, 'audit unavailable'); END");

        try {
            $this->seed(HistoricalSdmSeeder::class);
            $this->fail('History must not persist without its audit.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('historical_sdms', 0);
        $this->assertDatabaseCount('historical_sdm_imports', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    private function manifest(array $changes = []): array
    {
        $disk = Storage::fake('local');
        $disk->makeDirectory('seed-data');
        $path = $disk->path('seed-data/history.xlsx');
        $this->workbook($path, [$this->row(), $this->row()]);
        $manifest = ['version' => 1, 'imports' => [[
            'contractor_name' => 'Seed agency', 'file' => 'history.xlsx',
            'sheet' => 'previous records of JMD', 'sha256' => hash_file('sha256', $path), 'row_count' => 2,
            ...$changes,
        ]]];
        $disk->put('seed-data/historical-sdms.json', json_encode($manifest, JSON_THROW_ON_ERROR));

        return $manifest;
    }

    private function row(): array
    {
        return ['1', '1', '45658', 'AM/Ops/L-8E', '8 E', 'IGDA', 'Source employee', '009001', '012345678901', 'Cash shortage', 'Warning', '1667', '1', 'JMD.1', '', '5'];
    }

    private function workbook(string $path, array $rows): void
    {
        $headers = ['USN', 'SNO', 'DATE', 'MEMO NO', 'LINE', 'STATION', 'ON DUTY STAFF', 'FILE NO', 'Aadhar Number', 'BRIEF DETAIL OF VIOLATION', 'RECOMMENDED ACTIONS', 'PENALTY ON M/s JMD (in ₹)', 'Sr. No.', 'C&P No', '', 'Negative Points'];
        $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ([$headers, ...$rows] as $index => $row) {
            $number = $index + 1;
            $xml .= '<row r="'.$number.'">';
            foreach ($row as $column => $value) {
                $xml .= '<c r="'.chr(65 + $column).$number.'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1).'</t></is></c>';
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="previous records of JMD" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
    }
}
