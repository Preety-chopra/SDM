<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CmcMemoDeficiencyFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[TestWith([UserRole::ManagerOperations])]
    #[TestWith([UserRole::CmcTeamLead])]
    #[TestWith([UserRole::CmcStaff])]
    #[TestWith([UserRole::DeputyGeneralManager])]
    public function test_cmc_users_can_filter_memos_with_multiple_deficiencies_and_clear_the_filter(UserRole $role): void
    {
        $user = $this->user($role);
        $matching = $this->memo('MATCHING', [DeficiencyType::LateReporting, DeficiencyType::DressCode]);
        $other = $this->memo('OTHER', [DeficiencyType::DressCode]);
        $this->memo('OTHER-SERVICE', [DeficiencyType::LateReporting], SdmType::Ticketing);
        $filters = ['service_type' => SdmType::Cfs->value, 'deficiency_type' => DeficiencyType::LateReporting->value];

        $this->actingAs($user)->get(route('sdm-memos.index', $filters))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->pluck('id')->all() === [$matching->id])
            ->assertSee('data-column-filter="SDM deficiency"', false)
            ->assertSee('aria-label="Filter by SDM deficiency"', false)
            ->assertSee('value="late_reporting" selected', false)
            ->assertSee('All deficiencies');

        $this->get(route('sdm-memos.index', [...$filters, 'deficiency_type' => '']))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => $memos->total() === 2
                && $memos->contains('id', $matching->id) && $memos->contains('id', $other->id));
    }

    #[TestWith([UserRole::ManagerOperations, ['category' => 'received_from_lines', 'received_tab' => 'pending']])]
    #[TestWith([UserRole::ManagerOperations, ['category' => 'received_from_lines', 'received_tab' => 'processed']])]
    #[TestWith([UserRole::ManagerOperations, ['category' => 'processed_by_cmc']])]
    #[TestWith([UserRole::ManagerOperations, ['category' => 'approved_by_dgm_cmc']])]
    #[TestWith([UserRole::CmcTeamLead, ['category' => 'pending_cmc_staff_assignment']])]
    #[TestWith([UserRole::CmcTeamLead, ['category' => 'processed_by_cmc_staff', 'verification_tab' => 'pending']])]
    #[TestWith([UserRole::CmcTeamLead, ['category' => 'processed_by_cmc_staff', 'verification_tab' => 'processed']])]
    #[TestWith([UserRole::CmcTeamLead, ['category' => 'approved_by_dgm_cmc']])]
    #[TestWith([UserRole::CmcStaff, ['category' => 'pending']])]
    #[TestWith([UserRole::DeputyGeneralManager, ['category' => 'pending']])]
    public function test_deficiency_dropdown_is_available_on_cmc_category_and_queue_tables(UserRole $role, array $parameters): void
    {
        $this->actingAs($this->user($role))->get(route('sdm-memos.index', $parameters))
            ->assertOk()
            ->assertSee('data-column-filter="SDM deficiency"', false)
            ->assertSee('form="sdm-filter-form" name="deficiency_type"', false)
            ->assertSee('All deficiencies');
    }

    #[TestWith([null])]
    #[TestWith([SdmType::Cfs])]
    #[TestWith([SdmType::Ticketing])]
    #[TestWith([SdmType::WandW])]
    #[TestWith([SdmType::Marshal])]
    #[TestWith([SdmType::BaggageHandling])]
    public function test_cmc_staff_pending_dropdown_uses_the_creation_form_deficiencies(?SdmType $serviceType): void
    {
        $line = Line::query()->create(['code' => 'RED', 'name' => 'Red Line', 'is_active' => true]);
        $station = Station::query()->create([
            'code' => 'RED-01', 'name' => 'Central Station', 'line_id' => $line->id, 'is_active' => true,
        ]);
        $creator = $this->user(UserRole::Station);
        $creator->update(['station_id' => $station->id]);
        $form = $this->actingAs($creator)->get(route('sdm-memos.create'))->assertOk();
        $optionsByType = $form->viewData('deficiencyOptionsByType');
        $expected = ['' => 'All deficiencies'];
        foreach ($serviceType ? [$optionsByType[$serviceType->value]] : $optionsByType as $options) {
            foreach ($options as $option) {
                $expected[$option['value']] ??= $option['label'];
            }
        }

        $response = $this->actingAs($this->user(UserRole::CmcStaff))->get(route('sdm-memos.index', array_filter([
            'category' => 'pending', 'service_type' => $serviceType?->value,
        ])))->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $options = (new \DOMXPath($document))->query('//select[@name="deficiency_type"]/option');
        $actual = [];
        foreach ($options as $option) {
            $value = $option->getAttribute('value');
            $this->assertArrayNotHasKey($value, $actual);
            $actual[$value] = trim($option->textContent);
        }
        $this->assertSame($expected, $actual);
    }

    public function test_deficiency_filter_is_applied_before_pagination_and_preserved_in_links(): void
    {
        for ($index = 1; $index <= 16; $index++) {
            $this->memo('MATCH-'.$index, [DeficiencyType::LateReporting]);
        }
        $this->memo('NON-MATCH', [DeficiencyType::DressCode]);

        $response = $this->actingAs($this->user(UserRole::CmcStaff))->get(route('sdm-memos.index', [
            'deficiency_type' => 'late_reporting', 'page' => 2,
        ]))->assertOk();

        $memos = $response->viewData('memos');
        $this->assertSame(16, $memos->total());
        $this->assertCount(1, $memos->items());
        $this->assertStringContainsString('deficiency_type=late_reporting', $memos->url(1));
    }

    #[TestWith(['unknown'])]
    #[TestWith([['late_reporting']])]
    public function test_invalid_deficiency_filters_are_rejected(mixed $deficiency): void
    {
        $this->actingAs($this->user(UserRole::CmcStaff))->get(route('sdm-memos.index', [
            'deficiency_type' => $deficiency,
        ]))->assertRedirect()->assertSessionHasErrors('deficiency_type');
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    private function memo(string $serialNumber, array $deficiencies, SdmType $serviceType = SdmType::Cfs): ServiceDeficiencyMemo
    {
        $line = Line::query()->firstOrCreate(['code' => 'RED'], ['name' => 'Red Line', 'is_active' => true]);
        $station = Station::query()->firstOrCreate(['code' => 'RED-01'], [
            'name' => 'Central Station', 'line_id' => $line->id, 'is_active' => true,
        ]);
        $memo = ServiceDeficiencyMemo::query()->create([
            'station_id' => $station->id,
            'service_type' => $serviceType, 'serial_number' => $serialNumber,
            'status' => MemoStatus::Submitted, 'incident_date' => '2026-11-18',
        ]);
        foreach ($deficiencies as $type) {
            $memo->deficiencies()->create(['type' => $type]);
        }

        return $memo;
    }
}
