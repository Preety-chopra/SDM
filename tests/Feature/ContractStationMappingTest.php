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
