<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BaggageHandlingContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_creates_a_baggage_handling_contract_without_invented_penalty_clauses(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator);
        $contractor = Contractor::query()->create(['name' => 'Brady Services Private Ltd.', 'is_active' => true]);
        $line = Line::query()->create(['code' => 'AEL', 'name' => 'Airport Express Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'SJSU', 'name' => 'Shivaji Stadium', 'line_id' => $line->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('contracts.store'), [
            'contractor_id' => $contractor->id,
            'line_ids' => [$line->id],
            'station_ids' => [$station->id],
            'service_type' => 'baggage_handling',
            'contract_number' => 'OOMisc-1435',
            'title' => 'Operation of Baggage Handling System',
            'effective_from' => '2023-11-01',
            'effective_to' => '2027-10-31',
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $contract = ServiceContract::query()->sole();
        $this->assertSame('baggage_handling', $contract->service_type->value);
        $this->assertSame('BHS', $contract->service_type->code());
        $this->assertSame('draft', $contract->status);
        $this->assertSame('2023-11-01', $contract->effective_from->toDateString());
        $this->assertSame('2027-10-31', $contract->effective_to->toDateString());
        $this->assertSame([$station->id], $contract->stations()->pluck('stations.id')->all());
        $this->assertDatabaseCount('contract_penalty_clauses', 0);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'contract.created',
            'actor_id' => $administrator->id,
            'auditable_type' => ServiceContract::class,
            'auditable_id' => $contract->id,
        ]);
        $this->actingAs($administrator)->get(route('contracts.show', $contract))
            ->assertOk()->assertSee('Operation of Baggage Handling System');
    }

    public function test_baggage_handling_contract_form_has_no_defaults_from_other_services(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator);

        $this->actingAs($administrator)->get(route('contracts.create', ['service_type' => 'baggage_handling']))
            ->assertOk()
            ->assertSee('value="baggage_handling"', false)
            ->assertViewHas('serviceType', fn (SdmType $type): bool => $type->code() === 'BHS')
            ->assertViewHas('clauses', []);
    }

    public function test_baggage_handling_memo_uses_only_other_until_contractual_deficiencies_are_defined(): void
    {
        $line = Line::query()->create(['code' => 'AEL', 'name' => 'Airport Express Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'SJSU', 'name' => 'Shivaji Stadium', 'line_id' => $line->id, 'is_active' => true]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $user->assignRole(UserRole::Station);

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertViewHas('deficiencyOptionsByType', fn (array $options): bool => ($options['baggage_handling'] ?? null) === [
                ['value' => 'other', 'label' => DeficiencyType::Other->label(), 'serial_number' => null],
            ]);
        $this->post(route('sdm-memos.store'), [
            'service_type' => 'baggage_handling',
            'deficiencies' => ['other'],
            'deficiency_remarks' => ['other' => 'Baggage handling equipment was unavailable.'],
        ])->assertSessionDoesntHaveErrors()->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame('baggage_handling', $memo->service_type->value);
        $this->assertSame(DeficiencyType::Other, $memo->deficiencies()->sole()->type);
    }
}
