<?php

namespace Tests\Feature;

use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\Station;
use App\Models\User;
use App\Services\ServiceContractService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ContractStationMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_can_view_station_mappings_and_filter_by_service(): void
    {
        $central = $this->station('Central Junction');
        $north = $this->station('Northern Terminus');
        $ticketing = $this->contract($central, 'Ticketing Agency');
        $ticketing->stations()->attach([$central->id, $north->id]);
        $this->contract($central, 'Legacy Marshal Agency', SdmType::Marshal);
        $this->contract($central, 'Previous Ticketing Agency')->update(['status' => 'retired']);
        $this->contract($north, 'Previous Marshal Agency', SdmType::Marshal)->update(['status' => 'retired']);
        $this->contract($central, 'Draft Ticketing Agency')->update(['status' => 'draft']);

        $this->actingAs($this->user(UserRole::Administrator))
            ->get('/contract-station-mapping')
            ->assertOk()
            ->assertSeeInOrder(['Central Junction', 'Ticketing Agency', '01-04-2026', '31-03-2027', 'Northern Terminus', 'Ticketing Agency'])
            ->assertSee('Legacy Marshal Agency')
            ->assertSee('Retired / previous')
            ->assertDontSee('Previous Ticketing Agency')
            ->assertDontSee('Draft Ticketing Agency');

        $this->get('/contract-station-mapping?service_type=marshal')
            ->assertOk()
            ->assertSee('Legacy Marshal Agency')
            ->assertSee('Northern Terminus')
            ->assertDontSee('Ticketing Agency');

        $this->get('/contract-station-mapping?status=retired&service_type=token_vending')
            ->assertOk()
            ->assertSee('Previous Ticketing Agency')
            ->assertSee('href="'.e(route('contract-station-mapping.index', ['status' => 'published', 'service_type' => 'token_vending'])).'"', false)
            ->assertDontSee('Previous Marshal Agency')
            ->assertDontSee('Draft Ticketing Agency')
            ->assertDontSee('Change contractor');
    }

    public function test_station_name_search_finds_partial_matches_across_pages_and_keeps_the_service_filter(): void
    {
        $central = $this->station('Central Junction');
        $north = $this->station('Northern Terminus');

        foreach (range(1, 30) as $sequence) {
            Station::query()->create([
                'code' => 'CENTRAL-'.$sequence,
                'name' => 'Central Stop '.$sequence,
                'line_id' => $central->line_id,
                'sequence_no' => $sequence,
            ]);
        }

        $this->contract($north, 'Previous Ticketing Agency')->update(['status' => 'retired']);
        $this->contract($north, 'Previous Marshal Agency', SdmType::Marshal)->update(['status' => 'retired']);

        $this->actingAs($this->user(UserRole::Administrator));
        $this->get(route('contract-station-mapping.index'))->assertOk()->assertDontSeeText('Northern Terminus');

        $filters = ['status' => 'retired', 'service_type' => 'token_vending', 'station_search' => '  termIN  '];
        $publishedUrl = route('contract-station-mapping.index', [...$filters, 'status' => 'published', 'station_search' => 'termIN']);
        $this->get(route('contract-station-mapping.index', $filters))
            ->assertOk()
            ->assertSeeText('Northern Terminus')
            ->assertSeeText('Previous Ticketing Agency')
            ->assertDontSeeText('Previous Marshal Agency')
            ->assertDontSeeText('Central Junction')
            ->assertSee('href="'.e($publishedUrl).'"', false);

        $this->get(route('contract-station-mapping.index', ['station_search' => 'Missing']))
            ->assertOk()->assertSeeText('No stations match your search.');
    }

    public function test_both_tabs_group_stations_by_line_code_and_sort_stations_by_sequence(): void
    {
        $lineCodes = [...range(1, 10), 'AEL'];

        foreach (array_reverse($lineCodes) as $code) {
            $line = Line::query()->create(['code' => (string) $code, 'name' => 'Internal line name '.$code]);
            Station::query()->create(['code' => 'S-'.$code, 'name' => 'Station for '.$code, 'line_id' => $line->id, 'sequence_no' => 10]);

            if ($code === 1) {
                Station::query()->create(['code' => 'S-1-FIRST', 'name' => 'Zebra Station', 'line_id' => $line->id, 'sequence_no' => 2]);
                Station::query()->create(['code' => 'S-1-UNNUMBERED', 'name' => 'Aardvark Station', 'line_id' => $line->id]);
            }
        }

        $lineLabels = array_map(fn (int|string $code): string => $code === 'AEL' ? 'AEL' : 'Line '.$code, $lineCodes);

        $this->actingAs($this->user(UserRole::Administrator));

        foreach (['published', 'retired'] as $status) {
            $this->get(route('contract-station-mapping.index', ['status' => $status]))
                ->assertOk()
                ->assertSeeTextInOrder($lineLabels)
                ->assertSeeTextInOrder(['Line 1', 'Zebra Station', 'Station for 1', 'Aardvark Station', 'Line 2'])
                ->assertDontSeeText('Internal line name');
        }
    }

    public function test_administrator_can_move_one_station_to_another_contract_and_audit_the_change(): void
    {
        $central = $this->station('Central Junction');
        $north = $this->station('Northern Terminus');
        $west = $this->station('West End');
        $source = $this->contract($central, 'Existing Agency');
        $source->stations()->attach([$central->id, $north->id]);
        $source->lines()->attach([$central->line_id, $north->line_id]);
        $replacement = $this->contract($west, 'Replacement Agency');
        $this->contract($central, 'Marshal Agency', SdmType::Marshal);
        $administrator = $this->user(UserRole::Administrator);
        $url = $this->mappingUrl($central, $source);

        $this->actingAs($administrator)->get($url)
            ->assertOk()->assertSee('Replacement Agency')->assertDontSee('Marshal Agency');

        $this->patch($url.'?service_type=token_vending&station_search=Central', ['replacement_contract_id' => $replacement->id])
            ->assertRedirect('/contract-station-mapping?service_type=token_vending&station_search=Central');

        $this->assertSame([$north->id], $source->stations()->pluck('stations.id')->all());
        $this->assertSame([$north->line_id], $source->lines()->pluck('lines.id')->all());
        $this->assertSame($north->id, $source->fresh()->station_id);
        $this->assertSame($north->line_id, $source->fresh()->line_id);
        $this->assertEqualsCanonicalizing([$central->id, $west->id], $replacement->stations()->pluck('stations.id')->all());
        $this->assertEqualsCanonicalizing([$central->line_id, $west->line_id], $replacement->lines()->pluck('lines.id')->all());

        $audit = AuditLog::query()->where('event', 'contract.station_reassigned')->sole();
        $this->assertSame($administrator->id, $audit->actor_id);
        $this->assertSame($central->id, $audit->auditable_id);
        $this->assertSame($source->id, $audit->before_values['contract_id']);
        $this->assertSame($replacement->id, $audit->after_values['contract_id']);
    }

    public function test_moving_the_last_station_does_not_make_its_previous_contract_available_at_every_station(): void
    {
        $central = $this->station('Central Junction');
        $west = $this->station('West End');
        $source = $this->contract($central, 'Existing Agency');
        $replacement = $this->contract($west, 'Replacement Agency');
        $unrestricted = $this->contract($west, 'Unrestricted Legacy Agency');
        $unrestricted->update(['station_id' => null, 'line_id' => null]);

        $this->actingAs($this->user(UserRole::Administrator))
            ->patch($this->mappingUrl($central, $source), ['replacement_contract_id' => $replacement->id])
            ->assertRedirect('/contract-station-mapping');

        $this->assertNull($source->fresh()->station_id);
        $this->assertNull($source->fresh()->line_id);
        $this->assertFalse($source->stations()->exists());

        $contracts = app(ServiceContractService::class);
        $this->assertEqualsCanonicalizing([$replacement->id, $unrestricted->id], $contracts->memoContractorOptions($central->id)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$replacement->id, $unrestricted->id], $contracts->memoContractorOptions(null)->pluck('id')->all());

        $url = $this->mappingUrl($central, $source);
        $this->from($url)->patch($url, ['replacement_contract_id' => $replacement->id])
            ->assertRedirect($url)->assertSessionHasErrors('replacement_contract_id');
        $this->get($url)->assertRedirect('/contract-station-mapping');
        $this->assertSame(1, AuditLog::query()->where('event', 'contract.station_reassigned')->count());

        $this->patch($this->mappingUrl($central, $replacement), ['replacement_contract_id' => $source->id])
            ->assertRedirect('/contract-station-mapping');
        $this->assertSame($central->id, $source->fresh()->station_id);
        $this->assertFalse($source->fresh()->is_station_unassigned);
    }

    public function test_replacement_must_match_the_service_and_status_and_must_not_create_a_published_overlap(): void
    {
        $central = $this->station('Central Junction');
        $west = $this->station('West End');
        $source = $this->contract($central, 'Existing Agency');
        $otherService = $this->contract($west, 'Marshal Agency', SdmType::Marshal);
        $draft = $this->contract($west, 'Draft Agency');
        $draft->update(['status' => 'draft']);
        $inactive = $this->contract($west, 'Inactive Agency');
        $inactive->contractor->update(['is_active' => false]);
        $overlapping = $this->contract($west, 'Overlapping Agency');
        $overlapping->replicate()->fill(['station_id' => $central->id, 'line_id' => $central->line_id, 'contract_group' => (string) Str::ulid()])->save();

        $this->actingAs($this->user(UserRole::Administrator));

        $url = $this->mappingUrl($central, $source);
        foreach ([$source, $otherService, $draft, $inactive, $overlapping] as $replacement) {
            $this->from($url)->patch($url, ['replacement_contract_id' => $replacement->id])
                ->assertRedirect($url)->assertSessionHasErrors('replacement_contract_id');
        }

        $this->from($url)->patch($url, ['replacement_contract_id' => [$overlapping->id]])
            ->assertRedirect($url)->assertSessionHasErrors(['replacement_contract_id' => 'Select a valid replacement contract.']);

        $this->assertSame($central->id, $source->fresh()->station_id);
        $this->assertSame($west->id, $overlapping->fresh()->station_id);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'contract.station_reassigned']);
    }

    public function test_station_users_cannot_view_or_update_contract_station_mappings(): void
    {
        $station = $this->station('Central Junction');
        $contract = $this->contract($station, 'Existing Agency');

        $this->actingAs($this->user(UserRole::Station));
        $this->get('/contract-station-mapping')->assertForbidden();
        $this->get($this->mappingUrl($station, $contract))->assertForbidden();
        $this->patch($this->mappingUrl($station, $contract), [])->assertForbidden();
    }

    public function test_administrator_can_download_all_station_mappings_as_excel(): void
    {
        $central = $this->station('Central Junction');
        $central->update(['sequence_no' => 0]);
        $north = $this->station('Northern Terminus');
        $ticketing = $this->contract($central, 'Ticketing Agency');
        $ticketing->stations()->attach([$central->id, $north->id]);
        $this->contract($central, 'CFA Agency', SdmType::Cfs);
        $this->contract($central, 'W&W Agency', SdmType::WandW);
        $this->contract($central, 'Marshal Agency', SdmType::Marshal);
        $this->contract($central, 'Draft Agency')->update(['status' => 'draft']);
        $this->contract($central, 'Retired Agency')->update(['status' => 'retired']);
        $this->contract($central, 'Unassigned Agency')->update(['is_station_unassigned' => true]);
        $this->contract($central, 'Baggage Agency', SdmType::BaggageHandling);
        foreach (range(1, 31) as $sequence) {
            Station::query()->create([
                'code' => 'EXTRA-'.$sequence, 'name' => 'Extra Stop '.$sequence,
                'line_id' => $central->line_id, 'sequence_no' => $sequence,
            ]);
        }
        $administrator = $this->user(UserRole::Administrator);

        $response = $this->actingAs($administrator)->get('/contract-station-mapping/export?page=2');

        $response->assertOk()->assertDownload('station-contract-mapping.xlsx')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $rows = $this->workbookRows($response->getContent());
        $this->assertCount(34, $rows);
        $this->assertSame(['S.no.', 'Line Code', 'Station Code', 'TVS Agnecy', 'CFA Agency', 'W&W Agency', 'Marshal Agency'], $rows[0]);
        $this->assertSame(['1', 'CENTRAL', 'CENTRAL', 'Ticketing Agency', 'CFA Agency', 'W&W Agency', 'Marshal Agency'], $rows[1]);
        $this->assertSame(['2', 'CENTRAL', 'EXTRA-1', '', '', '', ''], $rows[2]);
        $this->assertSame(['33', 'NORTHERN', 'NORTHERN', 'Ticketing Agency', '', '', ''], $rows[33]);
        $audit = AuditLog::query()->where('event', 'contract.station_mapping_exported')->sole();
        $this->assertSame($administrator->id, $audit->actor_id);
        $this->assertSame(33, $audit->after_values['station_count']);
    }

    public function test_excel_download_respects_status_service_and_station_filters(): void
    {
        $central = $this->station('Central Junction');
        $north = $this->station('Northern Terminus');
        $this->contract($central, 'Published TVS');
        $this->contract($central, 'Previous TVS')->update(['status' => 'retired']);
        $this->contract($central, 'Previous Marshal', SdmType::Marshal)->update(['status' => 'retired']);
        $this->contract($north, 'Northern TVS')->update(['status' => 'retired']);

        $response = $this->actingAs($this->user(UserRole::Administrator))
            ->get('/contract-station-mapping/export?status=retired&service_type=token_vending&station_search=%20cENtr%20');

        $response->assertOk();
        $this->assertSame([
            ['S.no.', 'Line Code', 'Station Code', 'TVS Agnecy', 'CFA Agency', 'W&W Agency', 'Marshal Agency'],
            ['1', 'CENTRAL', 'CENTRAL', 'Previous TVS', '', '', ''],
        ], $this->workbookRows($response->getContent()));
    }

    public function test_excel_download_preserves_codes_and_agency_names_as_text_and_includes_multiple_agencies(): void
    {
        $lineTen = Line::query()->create(['code' => '10', 'name' => 'Line Ten']);
        $lineTwo = Line::query()->create(['code' => '2', 'name' => 'Line Two']);
        Station::query()->create(['code' => 'TEN', 'name' => 'Ten', 'line_id' => $lineTen->id]);
        $station = Station::query()->create(['code' => '001', 'name' => 'Two', 'line_id' => $lineTwo->id]);
        $this->contract($station, '=TVS & "Agency"');
        $second = $this->contract($station, 'Second Agency');
        $second->replicate()->fill(['version' => 2])->save();

        $response = $this->actingAs($this->user(UserRole::Administrator))->get('/contract-station-mapping/export');

        $response->assertOk();
        $rows = $this->workbookRows($response->getContent());
        $this->assertSame(['1', '2', '001', "=TVS & \"Agency\"\nSecond Agency", '', '', ''], $rows[1]);
        $this->assertSame(['2', '10', 'TEN', '', '', '', ''], $rows[2]);
    }

    public function test_excel_download_with_no_matching_stations_contains_the_headings(): void
    {
        $this->station('Central Junction');

        $response = $this->actingAs($this->user(UserRole::Administrator))
            ->get('/contract-station-mapping/export?station_search=Missing');

        $response->assertOk();
        $this->assertSame([
            ['S.no.', 'Line Code', 'Station Code', 'TVS Agnecy', 'CFA Agency', 'W&W Agency', 'Marshal Agency'],
        ], $this->workbookRows($response->getContent()));
    }

    public function test_mapping_page_links_to_excel_download_with_current_filters(): void
    {
        $filters = ['status' => 'retired', 'service_type' => 'marshal', 'station_search' => 'Central'];

        $this->actingAs($this->user(UserRole::Administrator))
            ->get(route('contract-station-mapping.index', $filters))
            ->assertOk()->assertSeeText('Download Excel')
            ->assertSee('href="'.e(url('/contract-station-mapping/export').'?'.http_build_query($filters)).'"', false);
    }

    public function test_guests_and_station_users_cannot_download_station_mappings(): void
    {
        $this->get('/contract-station-mapping/export')->assertRedirect(route('login'));
        $this->actingAs($this->user(UserRole::Station))
            ->get('/contract-station-mapping/export')->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', ['event' => 'contract.station_mapping_exported']);
    }

    private function workbookRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'mapping-test-');
        file_put_contents($path, $content);
        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($path));
            foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml'] as $part) {
                $this->assertNotFalse(simplexml_load_string($zip->getFromName($part)));
            }
            $sheet = simplexml_load_string($zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertNotFalse($sheet);
            $this->assertSame([], $sheet->xpath('//*[local-name()="f"]'));

            return array_map(fn ($row): array => array_map(
                fn ($cell): string => (string) ((string) $cell['t'] === 'inlineStr' ? $cell->is->t : $cell->v),
                iterator_to_array($row->children(), false),
            ), $sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]'));
        } finally {
            if ($zip->status === ZipArchive::ER_OK) {
                $zip->close();
            }
            unlink($path);
        }
    }

    private function station(string $name): Station
    {
        $code = Str::upper(Str::before($name, ' '));
        $line = Line::query()->create(['code' => $code, 'name' => $name.' Line', 'is_active' => true]);

        return Station::query()->create(['code' => $code, 'name' => $name, 'line_id' => $line->id, 'is_active' => true]);
    }

    private function contract(Station $station, string $agency, SdmType $type = SdmType::Ticketing): ServiceContract
    {
        return ServiceContract::query()->create([
            'contract_group' => (string) Str::ulid(),
            'contractor_id' => Contractor::factory()->create(['name' => $agency])->id,
            'line_id' => $station->line_id,
            'station_id' => $station->id,
            'service_type' => $type,
            'contract_number' => Str::slug($agency),
            'title' => $agency.' contract',
            'effective_from' => '2026-04-01',
            'effective_to' => '2027-03-31',
            'status' => 'published',
        ]);
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function mappingUrl(Station $station, ServiceContract $contract): string
    {
        return "/contract-station-mapping/{$station->id}/{$contract->id}";
    }
}
