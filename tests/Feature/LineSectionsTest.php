<?php

namespace Tests\Feature;

use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\AftCashCheckReport;
use App\Models\Contractor;
use App\Models\HistoricalSdm;
use App\Models\HistoricalSdmImport;
use App\Models\Line;
use App\Models\LineSection;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Services\ServiceContractService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class LineSectionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_can_divide_a_line_by_ranges_and_checkboxes_without_changing_old_memos(): void
    {
        [$line, $stations] = $this->line();
        $administrator = $this->administrator();
        $memo = $this->memo($stations[0], $administrator);
        $before = $memo->refresh()->getAttributes();

        $this->actingAs($administrator)->put('/lines/'.$line->id.'/sections', ['sections' => [
            ['code' => '6N', 'name' => 'Line-6N', 'selection_mode' => 'range', 'from_station_id' => $stations[1]->id, 'to_station_id' => $stations[0]->id],
            ['code' => '6S', 'name' => 'Line-6S', 'selection_mode' => 'stations', 'station_ids' => [$stations[2]->id]],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        $north = LineSection::query()->where('code', '6N')->sole();
        $this->assertSame([$stations[0]->id, $stations[1]->id], $north->station_ids);
        $this->assertSame($north->id, $stations[0]->fresh()->line_section_id);
        $this->assertSame($line->id, $stations[0]->fresh()->line_id);
        $this->assertSame($before, $memo->fresh()->getAttributes());
        $this->assertDatabaseHas('audit_logs', ['event' => 'line.sections_changed', 'actor_id' => $administrator->id, 'auditable_id' => $line->id]);
        $this->get('/lines/'.$line->id.'/sections')->assertOk()->assertSee('Line-6N')->assertSee('From station')->assertSee('To station');
        $this->get(route('stations.index'))->assertOk()->assertSee('Line-6N');
    }

    public function test_sections_can_be_redivided_then_merged_back_with_previous_definitions_retained(): void
    {
        [$line, $stations] = $this->line();
        $this->actingAs($this->administrator());
        $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', [$stations[0]->id]), $this->section('6S', [$stations[1]->id, $stations[2]->id])]])->assertSessionHasNoErrors();
        $old = LineSection::query()->where('code', '6N')->sole();

        $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', [$stations[0]->id, $stations[1]->id]), $this->section('6S', [$stations[2]->id])]])->assertSessionHasNoErrors();

        $this->assertNotNull($old->fresh()->retired_at);
        $this->assertSame([$stations[0]->id], $old->fresh()->station_ids);
        $this->assertNotSame($old->id, $stations[0]->fresh()->line_section_id);

        $this->put('/lines/'.$line->id.'/sections', ['sections' => []])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('line_sections', 4);
        $this->assertSame(0, LineSection::query()->whereNull('retired_at')->count());
        $this->assertNull($stations[0]->fresh()->line_section_id);
    }

    public function test_new_memos_use_the_section_and_existing_memos_keep_their_snapshot_when_edited_after_redivision(): void
    {
        [$line, $stations] = $this->line();
        $this->actingAs($this->administrator())->put('/lines/'.$line->id.'/sections', [
            'sections' => [$this->section('6N', $stations->modelKeys())],
        ])->assertSessionHasNoErrors();
        $issuer = User::factory()->create(['station_id' => $stations[0]->id]);
        $issuer->assignRole(UserRole::Station);

        $this->actingAs($issuer)->post(route('sdm-memos.store'), ['service_type' => SdmType::Cfs->value])->assertSessionHasNoErrors();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame('6N', $memo->line_code);
        $this->assertSame('Line-6N', $memo->line_name);
        $this->get(route('sdm-memos.edit', $memo))->assertOk()->assertSee('Line-6N');

        $this->actingAs($this->administrator())->put('/lines/'.$line->id.'/sections', ['sections' => []])->assertSessionHasNoErrors();
        $this->actingAs($issuer)->post(route('sdm-memos.update', $memo), ['service_type' => SdmType::Cfs->value])->assertSessionHasNoErrors();

        $this->assertSame('6N', $memo->fresh()->line_code);
        $this->assertSame('Line-6N', $memo->fresh()->line_name);
        $this->actingAs($this->administrator())->get(route('sdm-memos.index', ['line_code' => '6N']))
            ->assertOk()->assertSee('6N');
    }

    public function test_section_history_cannot_be_deleted_through_station_or_line_management(): void
    {
        [$line, $stations] = $this->line();
        $this->actingAs($this->administrator());
        $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', $stations->modelKeys())]])->assertSessionHasNoErrors();
        $this->put('/lines/'.$line->id.'/sections', ['sections' => []])->assertSessionHasNoErrors();

        $this->delete(route('stations.destroy', $stations[0]))->assertSessionHasErrors('station');
        $this->delete(route('lines.destroy', $line))->assertSessionHasErrors('line');
        $this->assertModelExists($stations[0]);
        $this->assertModelExists($line);
    }

    public function test_a_line_code_cannot_reuse_a_section_code(): void
    {
        [$line, $stations] = $this->line();
        $this->actingAs($this->administrator());
        $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', $stations->modelKeys())]])->assertSessionHasNoErrors();

        $this->post(route('lines.store'), ['code' => '6N', 'name' => 'Collision', 'is_active' => 1])->assertSessionHasErrors('code');
    }

    public function test_station_choices_at_login_include_the_current_section(): void
    {
        [$line, $stations] = $this->line();
        $nextLine = Line::query()->create(['code' => '7', 'name' => 'Line 7']);
        Station::query()->create(['code' => 'NEXT', 'name' => 'Other', 'line_id' => $nextLine->id]);
        $this->actingAs($this->administrator())->put('/lines/'.$line->id.'/sections', [
            'sections' => [$this->section('6N', $stations->modelKeys())],
        ])->assertSessionHasNoErrors();
        auth()->logout();

        $this->get(route('login'))->assertOk()->assertViewHas('stationOptions', fn ($options): bool => $options->pluck('label')->all() === [
            'L-6N Station 1', 'L-6N Station 2', 'L-6N Station 10', 'L-7 Other',
        ]);
    }

    public function test_imported_sdms_and_existing_cash_checks_are_unchanged_by_sections_and_merges(): void
    {
        [$line, $stations] = $this->line();
        $administrator = $this->administrator();
        $contractor = Contractor::query()->create(['name' => 'Agency']);
        $import = HistoricalSdmImport::query()->create([
            'contractor_id' => $contractor->id, 'source_file' => 'history.xlsx', 'source_sha256' => str_repeat('a', 64),
            'source_sheet' => 'History', 'row_count' => 1, 'summary' => [],
        ]);
        $historical = HistoricalSdm::query()->create([
            'import_id' => $import->id, 'contractor_id' => $contractor->id, 'station_id' => $stations[0]->id,
            'source_row' => 1, 'source_line' => '6', 'source_station' => 'S1', 'import_issues' => [],
        ]);
        $report = AftCashCheckReport::query()->create(['created_by' => $administrator->id, 'creator_name' => $administrator->name, 'status' => 'submitted']);
        $entry = $report->entries()->create(['station_id' => $stations[0]->id, 'station_name' => 'Old station name', 'line_code' => '6', 'sequence' => 1]);
        $historicalBefore = $historical->refresh()->getAttributes();
        $entryBefore = $entry->refresh()->getAttributes();
        $target = Line::query()->create(['code' => '7', 'name' => 'Line 7']);
        $this->actingAs($administrator);

        $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', $stations->modelKeys())]])->assertSessionHasNoErrors();
        $this->post('/lines/merge', ['line_ids' => [$line->id, $target->id], 'target_line_id' => $target->id])->assertSessionHasNoErrors();

        $this->assertSame($historicalBefore, $historical->fresh()->getAttributes());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame($target->id, $stations[0]->fresh()->line_id);
    }

    public function test_new_cash_checks_show_and_capture_the_current_section(): void
    {
        [$line, $stations] = $this->line();
        $this->actingAs($this->administrator())->put('/lines/'.$line->id.'/sections', [
            'sections' => [$this->section('6N', $stations->modelKeys())],
        ])->assertSessionHasNoErrors();
        $aft = User::factory()->create();
        $aft->assignRole(UserRole::Aft);

        $this->actingAs($aft)->post(route('aft-cash-check-reports.store'), ['action' => 'save', 'entries' => [['station_id' => $stations[0]->id]]])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('aft_cash_check_entries', ['station_id' => $stations[0]->id, 'line_code' => '6N']);
        $this->get(route('aft-cash-check-reports.create'))->assertOk()->assertSee('Station 1 (6N)');
    }

    #[TestWith(['overlap'])]
    #[TestWith(['gap'])]
    #[TestWith(['foreign'])]
    #[TestWith(['duplicate_code'])]
    #[TestWith(['invalid_range'])]
    #[TestWith(['unsequenced_range'])]
    public function test_invalid_divisions_are_rejected_atomically(string $case): void
    {
        [$line, $stations] = $this->line();
        $sections = [$this->section('6N', [$stations[0]->id, $stations[1]->id]), $this->section('6S', [$stations[2]->id])];
        if ($case === 'overlap') {
            $sections[1]['station_ids'][] = $stations[0]->id;
        } elseif ($case === 'gap') {
            $sections[0]['station_ids'] = [$stations[0]->id];
        } elseif ($case === 'foreign') {
            $other = Line::query()->create(['code' => '8', 'name' => 'Line 8']);
            $sections[0]['station_ids'][] = Station::query()->create(['code' => 'OTHER', 'name' => 'Other', 'line_id' => $other->id])->id;
        } elseif ($case === 'duplicate_code') {
            $sections[1]['code'] = '6N';
        } else {
            if ($case === 'unsequenced_range') {
                $stations[0]->update(['sequence_no' => null]);
            }
            $sections[0] = ['code' => '6N', 'name' => 'North', 'selection_mode' => 'range', 'from_station_id' => $stations[0]->id, 'to_station_id' => $case === 'invalid_range' ? 99999 : $stations[1]->id];
        }

        $this->actingAs($this->administrator())->put('/lines/'.$line->id.'/sections', ['sections' => $sections])
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('line_sections', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'line.sections_changed']);
    }

    public function test_merging_lines_preserves_memos_contract_coverage_and_contract_location_labels(): void
    {
        [$source, $stations] = $this->line();
        $target = Line::query()->create(['code' => '7', 'name' => 'Line 7']);
        $administrator = $this->administrator();
        $memo = $this->memo($stations[0], $administrator);
        $contract = ServiceContract::query()->create([
            'contract_group' => 'test', 'contractor_id' => $memo->contractor_id, 'line_id' => $source->id, 'station_id' => $stations[0]->id,
            'service_type' => SdmType::Ticketing, 'contract_number' => 'C-1', 'title' => 'Test',
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'status' => 'published',
        ]);
        $contract->lines()->attach($source);
        $contract->stations()->attach($stations[0]);
        $before = $memo->refresh()->getAttributes();
        $contractBefore = Arr::except($contract->refresh()->getAttributes(), ['location_snapshot']);
        $labels = app(ServiceContractService::class)->locationSummaries($contract->load(['lines', 'stations']))->all();
        $this->travel(1)->minutes();

        $this->actingAs($administrator)->post('/lines/merge', ['line_ids' => [$source->id, $target->id], 'target_line_id' => $target->id])
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($target->id, $stations[0]->fresh()->line_id);
        $this->assertFalse($source->fresh()->is_active);
        $this->assertSame($target->id, $source->fresh()->merged_into_line_id);
        $this->assertSame($before, $memo->fresh()->getAttributes());
        $this->assertSame([$stations[0]->id], $contract->fresh()->stations->modelKeys());
        $this->assertTrue(ServiceContract::query()->coversStations([$stations[0]->id])->whereKey($contract->id)->exists());
        $this->assertSame($labels, app(ServiceContractService::class)->locationSummaries($contract->fresh()->load(['lines', 'stations']))->all());
        $this->assertSame($contractBefore, Arr::except($contract->fresh()->getAttributes(), ['location_snapshot']));
        $this->assertDatabaseHas('audit_logs', ['event' => 'line.merged', 'actor_id' => $administrator->id]);
        $this->put(route('lines.update', $source), ['code' => '6', 'name' => 'Line 6', 'is_active' => 1])->assertSessionHasErrors('line');

        $this->post(route('contracts.revise', $contract))->assertSessionHasNoErrors();
        $revision = ServiceContract::query()->where('version', 2)->sole();
        $this->assertSame($target->id, $revision->line_id);
        $this->assertSame([$target->id], $revision->lines->modelKeys());
        $this->assertSame(['7 - Station 1 - Station 1'], app(ServiceContractService::class)->locationSummaries($revision->load('stations'))->all());

        $currentContract = $contract->fresh();
        app(ServiceContractRepositoryInterface::class)->setStations($currentContract, collect([$stations[1]->fresh()]), $administrator->id);
        $this->assertSame(['7 - Station 2 - Station 2'], app(ServiceContractService::class)->locationSummaries($currentContract->fresh()->load(['lines', 'stations']))->all());
    }

    public function test_merge_requires_two_active_lines_and_a_target_from_the_selection(): void
    {
        [$line] = $this->line();
        $other = Line::query()->create(['code' => '7', 'name' => 'Line 7']);
        $this->actingAs($this->administrator())->post('/lines/merge', ['line_ids' => [$line->id], 'target_line_id' => $other->id])
            ->assertSessionHasErrors();
        $this->assertTrue($line->fresh()->is_active);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'line.merged']);
    }

    public function test_audit_failure_rolls_back_section_membership(): void
    {
        [$line, $stations] = $this->line();
        $administrator = $this->administrator();
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"') && in_array('line.sections_changed', $query->bindings, true)) {
                throw new RuntimeException('Audit unavailable');
            }
        });
        $this->withoutExceptionHandling()->actingAs($administrator);

        try {
            $this->put('/lines/'.$line->id.'/sections', ['sections' => [$this->section('6N', $stations->modelKeys())]]);
            $this->fail('Expected an audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertDatabaseCount('line_sections', 0);
        $this->assertNull($stations[0]->fresh()->line_section_id);
    }

    public function test_guests_and_non_administrators_cannot_change_sections_or_merge_lines(): void
    {
        [$line] = $this->line();
        $this->get('/lines/'.$line->id.'/sections')->assertRedirect(route('login'));
        $this->put('/lines/'.$line->id.'/sections', ['sections' => []])->assertRedirect(route('login'));
        $this->post('/lines/merge', [])->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create());
        $this->get('/lines/'.$line->id.'/sections')->assertForbidden();
        $this->put('/lines/'.$line->id.'/sections', ['sections' => []])->assertForbidden();
        $this->post('/lines/merge', [])->assertForbidden();
    }

    private function line(): array
    {
        $line = Line::query()->create(['code' => '6', 'name' => 'Line 6']);
        $stations = collect([1, 2, 10])->map(fn (int $sequence): Station => Station::query()->create([
            'code' => 'S'.$sequence, 'name' => 'Station '.$sequence, 'line_id' => $line->id, 'sequence_no' => $sequence,
        ]));

        return [$line, new Collection($stations->all())];
    }

    private function section(string $code, array $ids): array
    {
        return ['code' => $code, 'name' => 'Line-'.$code, 'selection_mode' => 'stations', 'station_ids' => $ids];
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator);

        return $user;
    }

    private function memo(Station $station, User $creator): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Ticketing, 'station_id' => $station->id, 'station_name' => $station->name,
            'line_code' => '6', 'line_name' => 'Line 6', 'serial_number' => 'OLD-001',
            'contractor_id' => Contractor::query()->create(['name' => 'Agency'])->id, 'created_by' => $creator->id,
        ]);
    }
}
