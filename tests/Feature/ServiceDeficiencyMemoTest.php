<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserPermission;
use App\Enums\UserRole;
use App\Enums\WorkflowStage;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Support\DeficiencyCatalog;
use App\Support\PenaltyClauseTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ServiceDeficiencyMemoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('sdm.require_contract_workflow', false);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_create_form_loads_published_contractor_contracts_for_the_signed_in_station(): void
    {
        [$user, $line, $station] = $this->stationUserWithLine();
        $otherStation = Station::query()->create(['code' => 'OTHER', 'name' => 'Other station', 'line_id' => $line->id, 'is_active' => true]);
        $contractor = Contractor::query()->create(['name' => 'Ticketing Partner', 'is_active' => true]);
        $inactive = Contractor::query()->create(['name' => 'Inactive Partner', 'is_active' => false]);
        $attributes = [
            'contract_group' => 'memo-contractor-lookup', 'version' => 1,
            'contractor_id' => $contractor->id, 'line_id' => $line->id, 'station_id' => $otherStation->id,
            'service_type' => SdmType::Ticketing, 'contract_number' => 'LOOKUP-1', 'title' => 'Ticketing contract',
            'effective_from' => '2026-01-01', 'effective_to' => '2026-12-31', 'status' => 'published',
        ];
        $mapped = ServiceContract::query()->create($attributes);
        $mapped->stations()->sync([$station->id]);
        ServiceContract::query()->create([...$attributes, 'contract_group' => 'unmapped', 'contract_number' => 'OTHER-STATION']);
        ServiceContract::query()->create([...$attributes, 'contract_group' => 'draft', 'contract_number' => 'DRAFT', 'station_id' => $station->id, 'status' => 'draft']);
        ServiceContract::query()->create([...$attributes, 'contract_group' => 'retired', 'contract_number' => 'RETIRED', 'station_id' => $station->id, 'status' => 'retired']);
        ServiceContract::query()->create([...$attributes, 'contract_group' => 'inactive', 'contract_number' => 'INACTIVE', 'station_id' => $station->id, 'contractor_id' => $inactive->id]);
        $legacy = ServiceContract::query()->create([...$attributes, 'contract_group' => 'legacy', 'contract_number' => 'LEGACY', 'station_id' => $station->id, 'service_type' => SdmType::Cfs]);
        $global = ServiceContract::query()->create([...$attributes, 'contract_group' => 'global', 'contract_number' => 'GLOBAL', 'station_id' => null]);
        $overridden = ServiceContract::query()->create([...$attributes, 'contract_group' => 'overridden', 'contract_number' => 'OVERRIDDEN', 'station_id' => $station->id]);
        $overridden->stations()->sync([$otherStation->id]);

        $response = $this->actingAs($user)->get(route('sdm-memos.create'))->assertOk();

        $contracts = $response->viewData('contractorContracts');
        $this->assertSame(collect([$mapped->id, $legacy->id, $global->id])->sort()->values()->all(), $contracts->pluck('id')->sort()->values()->all());
        $this->assertSame([
            'id' => $mapped->id,
            'contractor_id' => $contractor->id,
            'service_type' => 'token_vending',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
        ], $contracts->firstWhere('id', $mapped->id));
        $response->assertSee('data-contractor-contracts=', false)
            ->assertSee('x-model="contractorId"', false);
    }

    public function test_station_role_receives_memo_permissions_and_administrator_receives_all_permissions(): void
    {
        [$stationUser, , $station] = $this->stationUserWithLine();
        $stationManager = User::factory()->create();
        $stationManager->assignRole(UserRole::StationManager->value);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->assertTrue($stationUser->can(UserPermission::CreateMemos->value));
        $this->assertFalse($stationUser->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($stationManager->can(UserPermission::CreateMemos->value));
        $this->assertTrue($stationManager->can('create', ServiceDeficiencyMemo::class));
        $this->assertTrue($administrator->can(UserPermission::ViewAllMemos->value));
        $this->assertTrue($administrator->can(UserPermission::ManageLines->value));

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Create SDM memo');

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($station->code.' · '.$station->name);
    }

    public function test_station_manager_can_create_and_submit_a_memo_for_the_station_selected_at_login(): void
    {
        [, $line, $station] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $lineManager = User::query()->role(UserRole::AssistantManager->value)->firstOrFail();
        $inactiveLineManager = User::factory()->disabled()->create(['employee_number' => 'AM-INACTIVE']);
        $inactiveLineManager->assignRole(UserRole::AssistantManager->value);
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->post(route('login'), [
            'username' => $stationManager->name,
            'password' => 'password',
            'station_id' => $station->id,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($line->name)
            ->assertSee($station->code.' · '.$station->name)
            ->assertSee('name="line_manager_employee_number"', false)
            ->assertSee('x-data="employeeAutocomplete"', false)
            ->assertSee('data-candidates=', false)
            ->assertSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertDontSee($inactiveLineManager->employee_number.' - '.$inactiveLineManager->name)
            ->assertDontSee('name="station_manager_employee_number"', false);

        $memoData = [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-STAFF-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Misbehavior->value],
            'deficiency_remarks' => [DeficiencyType::Misbehavior->value => 'Test incident'],
        ];

        $this->post(route('sdm-memos.store-and-submit'), $memoData)
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $inactiveLineManager->employee_number])
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $stationManager->employee_number])
            ->assertSessionHasErrors('line_manager_employee_number');
        $this->post(route('sdm-memos.store-and-submit'), [...$memoData, 'line_manager_employee_number' => $lineManager->employee_number])
            ->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->whereNotNull('submitted_at')->sole();
        $this->assertSame($station->id, $memo->station_id);
        $this->assertSame($line->name, $memo->line_name);
        $this->assertSame(MemoStatus::UnderReview, $memo->status);
        $this->assertSame(WorkflowStage::LineManager, $memo->current_stage);
        $this->assertSame($lineManager->id, $memo->approvals()->where('role', WorkflowStage::LineManager->value)->value('assigned_to'));
    }

    public function test_station_login_selection_defaults_the_create_form_station_and_line(): void
    {
        [$user] = $this->stationUserWithLine();
        $line = Line::query()->create(['code' => 'BLUE', 'name' => 'Blue Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'BLUE-01', 'name' => 'Blue Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password'])
            ->assertSessionHasErrors('station_id');

        $this->post(route('login'), ['username' => $user->name, 'password' => 'password', 'station_id' => $station->id])
            ->assertRedirect(route('sdm-memos.create', absolute: false))
            ->assertSessionHas('auth.station_id', $station->id);
        $this->assertAuthenticatedAs($user);

        $this->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee($line->code.' · '.$line->name)
            ->assertSee($station->code.' · '.$station->name);
    }

    public function test_create_form_displays_the_line_code_and_station_name_beside_memo_details(): void
    {
        [$user, $line, $station] = $this->stationUserWithLine();

        $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Memo details',
                'data-memo-location',
                '['.$line->code.' · '.$station->name.']',
            ], false);
    }

    public function test_cash_check_official_fields_default_to_the_logged_in_user_and_remain_editable(): void
    {
        [$user] = $this->stationUserWithLine();
        $user->update([
            'name' => 'Inspecting Officer',
            'designation' => 'Station Controller',
            'employee_number' => 'EMP-INSPECT-01',
        ]);

        $response = $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        foreach ([
            'cash_check[checking_official_name]' => $user->name,
            'cash_check[checking_official_designation]' => $user->designation,
            'cash_check[checking_official_employee_number]' => $user->employee_number,
        ] as $name => $expectedValue) {
            $input = $xpath->query(sprintf('//input[@name="%s"]', $name))->item(0);

            $this->assertInstanceOf(\DOMElement::class, $input);
            $this->assertSame($expectedValue, $input->getAttribute('value'));
            $this->assertFalse($input->hasAttribute('readonly'));
            $this->assertFalse($input->hasAttribute('disabled'));
        }
    }

    public function test_cash_check_time_defaults_to_the_current_ist_time_on_the_create_form(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:07:30', 'UTC'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('id="cash_check_time" type="time" name="cash_check[check_time]" value="15:37"', false);
    }

    public function test_cash_check_time_preserves_previously_entered_input_on_the_create_form(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:07:30', 'UTC'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)
            ->withSession(['_old_input' => ['cash_check' => ['check_time' => '09:20']]])
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('id="cash_check_time" type="time" name="cash_check[check_time]" value="09:20"', false);
    }

    public function test_cash_check_time_preserves_the_saved_time_on_the_edit_form(): void
    {
        $this->travelTo(Carbon::parse('2026-11-05 10:07:30', 'UTC'));
        [$user] = $this->stationUserWithLine();
        $memo = $this->draft($user, SdmType::Ticketing, 'CASH-TIME');
        $memo->cashCheck()->create(['checked_at' => '2026-11-04 08:25:00']);

        $this->actingAs($user)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('id="cash_check_time" type="time" name="cash_check[check_time]" value="08:25"', false);
    }

    public function test_cash_check_form_has_one_amount_with_exclusive_excess_short_radios_and_separate_hidden_cash(): void
    {
        [$user] = $this->stationUserWithLine();

        $response = $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Excess amount')
            ->assertSee('Short amount')
            ->assertSee('Hidden cash amount')
            ->assertSee('name="cash_check[amount_type]" value="excess"', false)
            ->assertSee('name="cash_check[amount_type]" value="short"', false)
            ->assertSee('name="cash_check[amount]"', false)
            ->assertSee('name="cash_check[hidden_cash_amount]"', false)
            ->assertDontSee('name="cash_check[excess_amount]"', false)
            ->assertDontSee('name="cash_check[short_amount]"', false)
            ->assertDontSee('name="cash_check[difference_amount]"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//input[@type="radio" and @name="cash_check[amount_type]"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="cash_check[amount_type]" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@type="number" and @name="cash_check[amount]"]')->length);
    }

    #[TestWith(['excess', '50.25', '50.25', '50.25', '', '50.25'])]
    #[TestWith(['short', '50.25', '-50.25', '', '50.25', '50.25'])]
    #[TestWith(['excess', '0', '0.00', '0.00', '', '0.00'])]
    public function test_cash_amount_and_radio_choice_are_saved_audited_and_restored_on_edit(string $type, ?string $amount, ?string $difference, string $savedExcess, string $savedShort, string $savedAmount): void
    {
        [$user] = $this->stationUserWithLine();

        $response = $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['amount_type' => $type, 'amount' => $amount, 'hidden_cash_amount' => '25.75'],
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();
        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame($difference, $memo->cashCheck->difference_amount);
        $this->assertSame('25.75', $memo->cashCheck->hidden_cash_amount);
        $audit = AuditLog::query()->where('auditable_type', ServiceDeficiencyMemo::class)->where('auditable_id', $memo->id)->latest('id')->firstOrFail();
        $this->assertSame($difference, data_get($audit->after_values, 'cash_check.difference_amount'));
        $this->assertSame('25.75', data_get($audit->after_values, 'cash_check.hidden_cash_amount'));
        $this->assertSame($savedExcess === '' ? null : $savedExcess, data_get($audit->after_values, 'cash_check.excess_amount'));
        $this->assertSame($savedShort === '' ? null : $savedShort, data_get($audit->after_values, 'cash_check.short_amount'));

        $edit = $this->get(route('sdm-memos.edit', $memo))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($edit->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['amount' => $savedAmount, 'hidden_cash_amount' => '25.75'] as $field => $expected) {
            $input = $xpath->query(sprintf('//input[@name="cash_check[%s]"]', $field))->item(0);
            $this->assertInstanceOf(\DOMElement::class, $input);
            $this->assertSame($expected, $input->getAttribute('value'));
        }
        $radio = $xpath->query('//input[@name="cash_check[amount_type]" and @checked]');
        $this->assertSame(1, $radio->length);
        $this->assertSame($type, $radio->item(0)->getAttribute('value'));
    }

    #[TestWith([null])]
    #[TestWith([''])]
    public function test_blank_cash_amount_does_not_require_or_restore_a_default_type(?string $amount): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['amount' => $amount, 'hidden_cash_amount' => '25.75'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertNull($memo->cashCheck->difference_amount);
        $this->assertSame('25.75', $memo->cashCheck->hidden_cash_amount);
        $response = $this->get(route('sdm-memos.edit', $memo))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//input[@name="cash_check[amount_type]" and @checked]')->length);
    }

    #[TestWith(['excess_amount', '-1'])]
    #[TestWith(['short_amount', '-1'])]
    #[TestWith(['excess_amount', '10000000000'])]
    #[TestWith(['short_amount', 'invalid'])]
    public function test_invalid_cash_amounts_are_rejected(string $field, string $amount): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => [$field => $amount],
        ])->assertSessionHasErrors('cash_check.'.$field);

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    public function test_excess_and_short_amounts_cannot_both_be_positive(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['excess_amount' => '10', 'short_amount' => '20'],
        ])->assertSessionHasErrors([
            'cash_check.excess_amount' => 'Enter either an excess amount or a short amount, not both.',
            'cash_check.short_amount' => 'Enter either an excess amount or a short amount, not both.',
        ]);

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    public function test_cash_amount_edits_replace_the_previous_amount_and_audit_the_change(): void
    {
        [$user] = $this->stationUserWithLine();
        $memo = $this->draft($user, SdmType::Ticketing, 'CASH-EDIT');
        $memo->cashCheck()->create(['difference_amount' => '-50.25', 'hidden_cash_amount' => '25.75']);

        $this->actingAs($user)->post(route('sdm-memos.update', $memo), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['amount_type' => 'excess', 'amount' => '12.50', 'hidden_cash_amount' => null],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $memo->refresh();
        $this->assertSame('12.50', $memo->cashCheck->difference_amount);
        $this->assertNull($memo->cashCheck->hidden_cash_amount);
        $audit = AuditLog::query()->where('event', 'memo.updated')->where('auditable_id', $memo->id)->sole();
        $this->assertSame('50.25', data_get($audit->before_values, 'cash_check.short_amount'));
        $this->assertSame('12.50', data_get($audit->after_values, 'cash_check.excess_amount'));
        $this->assertNull(data_get($audit->after_values, 'cash_check.short_amount'));

        $this->get(route('sdm-memos.show', $memo))->assertOk()
            ->assertSeeInOrder(['Excess amount', '12.50', 'Short amount', 'Hidden cash amount']);
    }

    public function test_invalid_cash_amount_submission_preserves_the_amount_radio_choice_and_hidden_cash(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->from(route('sdm-memos.create'))->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['amount_type' => 'short', 'amount' => '-20.50', 'hidden_cash_amount' => '30.75'],
        ])->assertRedirect(route('sdm-memos.create'))->assertSessionHasErrors('cash_check.amount');

        $response = $this->get(route('sdm-memos.create'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        foreach (['amount' => '-20.50', 'hidden_cash_amount' => '30.75'] as $field => $expected) {
            $input = $xpath->query(sprintf('//input[@name="cash_check[%s]"]', $field))->item(0);
            $this->assertInstanceOf(\DOMElement::class, $input);
            $this->assertSame($expected, $input->getAttribute('value'));
        }
        $radio = $xpath->query('//input[@name="cash_check[amount_type]" and @checked]');
        $this->assertSame(1, $radio->length);
        $this->assertSame('short', $radio->item(0)->getAttribute('value'));
    }

    #[TestWith(['excess', '-1', 'cash_check.amount'])]
    #[TestWith(['short', '10000000000', 'cash_check.amount'])]
    #[TestWith(['short', 'invalid', 'cash_check.amount'])]
    #[TestWith(['both', '10', 'cash_check.amount_type'])]
    #[TestWith([null, '10', 'cash_check.amount_type'])]
    #[TestWith([null, '0', 'cash_check.amount_type'])]
    #[TestWith(['', '10', 'cash_check.amount_type'])]
    #[TestWith([['excess', 'short'], '10', 'cash_check.amount_type'])]
    public function test_cash_amount_requires_a_valid_single_type_and_nonnegative_amount(string|array|null $type, string $amount, string $error): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => ['amount_type' => $type, 'amount' => $amount],
        ])->assertSessionHasErrors($error);

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    public function test_create_form_provides_every_ssc_penalty_clause_for_each_sdm_type(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)
            ->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('data-deficiency-options=', false)
            ->assertViewHas('deficiencyOptionsByType', function (array $optionsByType): bool {
                foreach (SdmType::cases() as $type) {
                    $expectedDescriptions = collect(PenaltyClauseTemplates::for($type))
                        ->pluck('description');
                    if ($type !== SdmType::BaggageHandling) {
                        $expectedDescriptions->splice(3, 1, [
                            'Late reporting',
                            'Leaving duty before scheduled time or leaving without informing the on-duty SM/SC',
                        ]);
                    }
                    $expectedDescriptions = $expectedDescriptions->push(DeficiencyType::Other->label())->all();
                    $options = collect($optionsByType[$type->value] ?? []);

                    if ($options->pluck('label')->all() !== $expectedDescriptions) {
                        return false;
                    }
                }

                return true;
            });
    }

    public function test_a_deficiency_must_belong_to_the_selected_sdm_type(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'deficiencies' => ['undervalued_tickets'],
        ])->assertSessionHasErrors('deficiencies.0');

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => ['undervalued_tickets'],
        ])->assertRedirect();

        $this->assertSame('undervalued_tickets', ServiceDeficiencyMemo::query()->sole()->deficiencies()->sole()->type->value);
    }

    #[TestWith([SdmType::Cfs])]
    #[TestWith([SdmType::Ticketing])]
    #[TestWith([SdmType::WandW])]
    #[TestWith([SdmType::Marshall])]
    public function test_leaving_duty_is_separate_from_late_reporting_and_can_be_submitted_without_reporting_times(SdmType $type): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Duty Partner', 'is_active' => true]);

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertViewHas('deficiencyOptionsByType', function (array $options) use ($type): bool {
                return array_slice($options[$type->value], 3, 2) === [
                    ['value' => 'late_reporting', 'label' => 'Late reporting', 'serial_number' => 4],
                    ['value' => 'leaving_duty', 'label' => 'Leaving duty before scheduled time or leaving without informing the on-duty SM/SC', 'serial_number' => 4],
                ];
            });

        $this->post(route('sdm-memos.store-and-submit'), [
            'service_type' => $type->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '16:30',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-DUTY',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => ['leaving_duty'],
            'deficiency_remarks' => ['leaving_duty' => 'Left without informing the on-duty SM.'],
        ])->assertRedirect(route('sdm-memos.index'))->assertSessionHasNoErrors();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $deficiency = $memo->deficiencies()->sole();
        $this->assertSame('leaving_duty', $deficiency->type->value);
        $this->assertSame('Left without informing the on-duty SM.', $deficiency->remarks);
        $this->assertNull($deficiency->lateReporting);
        $this->assertDatabaseHas('audit_logs', ['event' => 'memo.submitted', 'auditable_id' => $memo->id]);
        $this->get(route('sdm-memos.show', $memo))->assertOk()
            ->assertSee('Leaving duty before scheduled time or leaving without informing the on-duty SM/SC');
    }

    public function test_station_user_can_save_a_draft_with_every_available_deficiency(): void
    {
        [$user] = $this->stationUserWithLine();
        $types = DeficiencyCatalog::valuesFor(SdmType::Ticketing);

        $response = $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => $types,
        ]);

        $response->assertSessionHasNoErrors();
        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertSame($types, $memo->deficiencies()->orderBy('id')->pluck('type')->map(fn (DeficiencyType $type): string => $type->value)->all());
    }

    public function test_station_user_can_save_partial_draft_but_submission_is_strict(): void
    {
        [$user] = $this->stationUserWithLine();

        $response = $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-100',
        ]);
        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertSame(MemoStatus::Draft, $memo->status);

        $this->actingAs($user)->from(route('sdm-memos.edit', $memo))->post(route('sdm-memos.update', $memo), [
            'service_type' => SdmType::Cfs->value,
            'staff_name' => '',
            'employee_number' => '',
            'deficiencies' => [DeficiencyType::LateReporting->value],
            'late_reporting' => [],
        ])->assertSessionHasNoErrors()->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertDatabaseHas('service_deficiency_memos', [
            'id' => $memo->id,
            'status' => MemoStatus::Draft->value,
            'staff_name' => null,
            'employee_number' => null,
        ]);

        $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-100',
        ])->assertSessionHasErrors(['contractor_id', 'incident_date', 'incident_time', 'staff_name', 'employee_number', 'deficiencies']);
    }

    public function test_station_controller_can_create_a_draft_without_a_serial_number(): void
    {
        [$stationController] = $this->stationUserWithLine();

        $response = $this->actingAs($stationController)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();

        $response->assertRedirect(route('sdm-memos.edit', $memo));
        $this->assertNull($memo->serial_number);
    }

    public function test_creator_can_delete_a_draft_and_its_stored_attachments(): void
    {
        Storage::fake('local');
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-DELETE-1',
            'attachments' => [UploadedFile::fake()->create('draft-proof.pdf', 25, 'application/pdf')],
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $attachmentPath = $memo->attachments()->sole()->storage_path;
        Storage::disk('local')->assertExists($attachmentPath);

        $this->actingAs($user)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('Delete draft')
            ->assertSee(route('sdm-memos.destroy', $memo), false);

        $this->actingAs($user)->delete(route('sdm-memos.destroy', $memo))
            ->assertRedirect(route('sdm-memos.drafts'))
            ->assertSessionHas('status', 'Draft deleted.');

        $this->assertNull(ServiceDeficiencyMemo::withTrashed()->find($memo->id));
        Storage::disk('local')->assertMissing($attachmentPath);
    }

    public function test_station_manager_can_delete_their_own_draft(): void
    {
        [, , $station] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'OWN-SM-DRAFT',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => MemoStatus::Draft->value,
            'created_by' => $stationManager->id,
        ]);

        $otherStationManager = User::factory()->create();
        $otherStationManager->assignRole(UserRole::StationManager->value);
        ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'OTHER-SM-DRAFT',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'status' => MemoStatus::Draft->value,
            'created_by' => $otherStationManager->id,
        ]);

        $this->withSession(['auth.station_id' => $station->id])
            ->actingAs($stationManager)
            ->get(route('sdm-memos.index', ['status' => 'drafts_own']))
            ->assertOk()
            ->assertSee('OWN-SM-DRAFT')
            ->assertDontSee('OTHER-SM-DRAFT')
            ->assertSee('href="'.route('sdm-memos.edit', $memo).'"', false)
            ->assertSee(route('sdm-memos.destroy', $memo), false)
            ->assertSee('Cancel');

        $this->actingAs($stationManager)
            ->delete(route('sdm-memos.destroy', $memo))
            ->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHas('status', 'Draft deleted.');

        $this->assertNull(ServiceDeficiencyMemo::withTrashed()->find($memo->id));
    }

    public function test_another_user_and_the_creator_after_submission_cannot_delete_the_memo(): void
    {
        [$owner] = $this->stationUserWithLine('RED');
        [$other] = $this->stationUserWithLine('GREEN');
        $memo = $this->draft($owner, SdmType::Cfs, 'CFS-DELETE-2');

        $this->actingAs($other)->delete(route('sdm-memos.destroy', $memo))->assertForbidden();
        $this->assertModelExists($memo);

        $memo->update(['status' => MemoStatus::Submitted->value]);

        $this->actingAs($owner)->delete(route('sdm-memos.destroy', $memo))->assertForbidden();
        $this->assertModelExists($memo);
    }

    public function test_station_user_can_submit_a_complete_memo_without_saving_a_draft_first(): void
    {
        $this->travelTo(Carbon::parse('2026-09-25 10:30:00'));

        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-10',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Other service deficiency.'],
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();
        $response->assertRedirect(route('sdm-memos.index'));
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertNotNull($memo->submitted_at);
        $this->assertSame($user->id, $memo->created_by);
        $this->assertSame('14:25', $memo->incident_time);
        $this->assertSame('RED/RED-01/CFA/2026/09/01', $memo->serial_number);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Incident time (IST)')
            ->assertSee('14:25');
    }

    public function test_submission_requires_outsourced_staff_acknowledgement(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-ACKNOWLEDGEMENT',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'deficiencies' => [DeficiencyType::Other->value],
        ])->assertSessionHasErrors('outsourced_staff_acknowledged');

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    public function test_submission_generates_a_unique_monthly_sdm_serial_number(): void
    {
        Carbon::setTestNow('2026-09-25 10:30:00');

        [$firstUser, $firstLine, $firstStation] = $this->stationUserWithLine('L-1E');
        $firstStation->update(['code' => 'RHE']);
        [$secondUser, $secondLine, $secondStation] = $this->stationUserWithLine('L-5');
        $secondStation->update(['code' => 'APMN']);
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($firstUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '25-09-2026',
            'incident_time' => '09:00',
            'staff_name' => 'First Staff Member',
            'employee_number' => 'EMP-101',
            'station_manager_employee_number' => 'L-1E-SM-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'First deficiency.'],
        ])->assertRedirect(route('sdm-memos.index'));

        $this->actingAs($secondUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Ticketing->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '25-09-2026',
            'incident_time' => '09:15',
            'staff_name' => 'Second Staff Member',
            'employee_number' => 'EMP-102',
            'station_manager_employee_number' => 'L-5-SM-1',
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::Other->value],
            'deficiency_remarks' => [DeficiencyType::Other->value => 'Second deficiency.'],
        ])->assertRedirect(route('sdm-memos.index'));

        $this->assertSame('L-1E', $firstLine->code);
        $this->assertSame('L-5', $secondLine->code);
        $this->assertSame([
            'L-1E/RHE/CFA/2026/09/01',
            'L-5/APMN/TVS/2026/09/02',
        ], ServiceDeficiencyMemo::query()->orderBy('id')->pluck('serial_number')->all());
    }

    public function test_memo_details_display_the_line_code_and_name_separated_by_a_comma(): void
    {
        [$user, $line] = $this->stationUserWithLine('RED');
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-LINE-1');
        $line->update(['code' => 'BLUE', 'name' => 'Blue Line']);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('RED, RED Line');
    }

    public function test_memo_details_display_the_late_reporting_delay_in_minutes(): void
    {
        [$user] = $this->stationUserWithLine();
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-DELAY-1');
        $deficiency = $memo->deficiencies()->create(['type' => DeficiencyType::LateReporting->value]);
        $deficiency->lateReporting()->create([
            'booked_shift_from' => '09:00',
            'booked_shift_to' => '17:00',
            'actual_reporting_time' => '09:25',
        ]);

        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Delay: 25 minutes');

        $deficiency->lateReporting->update(['actual_reporting_time' => '10:25']);
        $this->actingAs($user)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Delay: 1 hour 25 minutes');
    }

    public function test_complete_ticketing_memo_persists_conditional_sections_and_private_attachment(): void
    {
        Storage::fake('local');
        [$user, , $station] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);
        $complaintStation = Station::query()->create(['code' => 'CMP', 'name' => 'Complaint Station', 'line_id' => $station->line_id, 'is_active' => true]);
        $memo = $this->draft($user, SdmType::Ticketing, 'TV-100');

        $response = $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-100',
            'contractor_id' => $contractor->id,
            'incident_date' => '17-08-2026',
            'incident_time' => '21:35',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-9',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value, DeficiencyType::DressCode->value, DeficiencyType::CashCheck->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
            'late_reporting' => ['booked_shift_from' => '09:00', 'booked_shift_to' => '17:00', 'actual_reporting_time' => '09:25'],
            'public_complaint' => ['number' => 'PC-10', 'station_id' => $complaintStation->id],
            'cash_check' => [
                'tom_ccc_number' => 'TOM-2',
                'end_of_shift_receipt_number' => 'EOS-25',
                'check_date' => '2026-08-17',
                'check_time' => '21:35',
                'difference_amount' => '-50.00',
                'hidden_cash_amount' => '100.00',
                'checking_official_name' => 'Checking Officer',
                'checking_official_designation' => 'Manager',
                'checking_official_employee_number' => 'EMP-55',
                'remarks' => 'Verified at the end of shift.',
            ],
            'attachments' => [UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf')],
        ]);

        $response->assertRedirect(route('sdm-memos.index'));
        $memo->refresh();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame('Metro Services', $memo->contractor_name);
        $this->assertCount(3, $memo->deficiencies);
        $this->assertSame('PC-10', $memo->publicComplaint->complaint_serial_number);
        $this->assertSame('-50.00', $memo->cashCheck->difference_amount);
        $this->assertSame('17-08-2026 21:35', $memo->cashCheck->checked_at->format('d-m-Y H:i'));
        $this->assertSame('Checking Officer', $memo->cashCheck->checking_official_name);
        $this->assertSame('100.00', $memo->cashCheck->hidden_cash_amount);
        Storage::disk('local')->assertExists($memo->attachments()->sole()->storage_path);
    }

    public function test_saved_late_reporting_times_can_be_resubmitted_with_database_seconds(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);
        $memo = $this->draft($user, SdmType::Cfs, 'CFS-LATE-REPORTING');
        $deficiency = $memo->deficiencies()->create(['type' => DeficiencyType::LateReporting->value]);
        $deficiency->lateReporting()->create([
            'booked_shift_from' => '09:00:00',
            'booked_shift_to' => '17:00:00',
            'actual_reporting_time' => '09:25:00',
        ]);

        $this->actingAs($user)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('value="09:00"', false)
            ->assertDontSee('value="09:00:00"', false);

        $this->actingAs($user)->post(route('sdm-memos.submit', $memo), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFS-LATE-REPORTING',
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '09:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-LATE',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value],
            'late_reporting' => [
                'booked_shift_from' => '09:00:00',
                'booked_shift_to' => '17:00:00',
                'actual_reporting_time' => '09:25:00',
            ],
        ])->assertRedirect(route('sdm-memos.index'));

        $memo->refresh();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame('09:25', $memo->deficiencies()->sole()->lateReporting->actual_reporting_time);
    }

    public function test_ticketing_late_reporting_submission_ignores_hidden_cash_check_defaults(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-LATE-REPORTING',
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '09:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-LATE-TV',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::LateReporting->value],
            'late_reporting' => [
                'booked_shift_from' => '09:00',
                'booked_shift_to' => '17:00',
                'actual_reporting_time' => '09:25',
            ],
            'cash_check' => [
                'check_date' => '2026-08-18',
                'check_time' => '09:25',
            ],
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertNull($memo->cashCheck);
    }

    public function test_create_form_exposes_visible_multi_selection_and_requested_cash_check_fields(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00', 'UTC'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('<title>Create Service Deficiency Memo</title>', false)
            ->assertSee('RED · RED Line')
            ->assertSee('data-selected-deficiencies', false)
            ->assertSee(route('sdm-memos.store-and-submit'), false)
            ->assertSee('Submit memo')
            ->assertSee('data-sdm-confirm', false)
            ->assertSee('Are you sure you want to proceed?')
            ->assertDontSee('SDM number')
            ->assertDontSee('name="serial_number"', false)
            ->assertSee('Proceed')
            ->assertSee('id="incident_date" type="date" name="incident_date"', false)
            ->assertSee('Time of incident (HH:MM, IST)')
            ->assertSee('id="incident_time" type="time" name="incident_time"', false)
            ->assertSee('value="15:30"', false)
            ->assertSee('required', false)
            ->assertSee('cash_check[check_date]', false)
            ->assertSee('type="date"', false)
            ->assertSee('max="2026-08-18"', false)
            ->assertSee('value="2026-08-18"', false)
            ->assertSee('cash_check[check_time]', false)
            ->assertSee('Cash-check remarks (if any)')
            ->assertDontSee('cash_check[operator_name]', false)
            ->assertDontSee('cash_check[checked_at]', false);
    }

    public function test_create_form_always_shows_one_optional_general_remarks_field(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Remarks (optional)')
            ->assertSee('name="station_record_brief_remarks"', false)
            ->assertDontSee('name="deficiency_remarks', false)
            ->assertDontSee('Poor Dress Code / Failure to Follow Instructions remarks')
            ->assertDontSee('Misbehavior with Customer / Staff remarks');
    }

    public function test_dress_code_and_misbehavior_can_be_submitted_without_deficiency_specific_remarks(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);

        $response = $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '14:25',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-OPTIONAL-REMARKS',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [
                DeficiencyType::DressCode->value,
                DeficiencyType::Misbehavior->value,
            ],
            'station_record_brief_remarks' => 'General optional context for the SDM.',
        ]);

        $memo = ServiceDeficiencyMemo::query()->sole();

        $response->assertRedirect(route('sdm-memos.index'));
        $this->assertSame('General optional context for the SDM.', $memo->station_record_brief_remarks);
        $this->assertTrue($memo->deficiencies->every(fn ($deficiency): bool => $deficiency->remarks === null));
    }

    public function test_create_form_shows_the_staff_acknowledgement_and_conditionally_displays_complaint_documents(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSeeInOrder([
                'Deficiencies',
                'I have received acknowledgement from outsourced staff',
                'Complaint and supporting documents',
            ])
            ->assertSeeInOrder([
                'id="outsourced_staff_acknowledged"',
                'name="outsourced_staff_acknowledged"',
                'type="checkbox"',
                'value="1"',
                'required',
                'aria-required="true"',
            ], false)
            ->assertSee('formnovalidate', false)
            ->assertSee('x-show="has(\'public_complaint\')"', false)
            ->assertSee('x-cloak', false);
    }

    public function test_station_controller_create_form_has_a_clear_submit_forwarding_prompt(): void
    {
        [$user] = $this->stationUserWithLine();
        $stationManager = User::query()->role(UserRole::StationManager->value)->firstOrFail();
        $lineManager = User::query()->role(UserRole::AssistantManager->value)->firstOrFail();
        $inactiveStationManager = User::factory()->disabled()->create(['employee_number' => 'SM-INACTIVE']);
        $inactiveStationManager->assignRole(UserRole::StationManager->value);

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Forwarding on submission')
            ->assertSee('Enter the employee number of the SM who should receive this SDM immediately after submission.')
            ->assertSee('name="station_manager_employee_number"', false)
            ->assertSee('data-employee-autocomplete', false)
            ->assertSee($stationManager->employee_number.' - '.$stationManager->name)
            ->assertDontSee($inactiveStationManager->employee_number.' - '.$inactiveStationManager->name)
            ->assertDontSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertSee(route('sdm-memos.store-and-submit'), false);
    }

    public function test_custom_station_controller_role_sees_the_submit_forwarding_prompt(): void
    {
        [, , $station] = $this->stationUserWithLine();
        $role = Role::findOrCreate('station_controller');
        $role->givePermissionTo([
            UserPermission::CreateMemos->value,
            UserPermission::ViewOwnMemos->value,
            UserPermission::UpdateOwnDraftMemos->value,
            UserPermission::SubmitOwnDraftMemos->value,
        ]);
        $controller = User::factory()->create(['station_id' => $station->id]);
        $controller->assignRole($role->name);

        $this->actingAs($controller)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Forwarding on submission')
            ->assertSee('name="station_manager_employee_number"', false);
    }

    public function test_overcharging_exposes_a_conditional_amount_text_field(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))->assertOk()
            ->assertSee('x-show="has(\'overcharging\')"', false)
            ->assertSee('Overcharged amount (₹)')
            ->assertSee('name="overcharge_amount"', false)
            ->assertSee(':disabled="!has(\'overcharging\')"', false);
    }

    #[TestWith(['125.50', '125.50'])]
    #[TestWith(['0', '0.00'])]
    #[TestWith(['9999999999.99', '9999999999.99'])]
    public function test_overcharged_amount_is_saved_restored_displayed_and_audited(string $amount, string $expected): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => ['overcharging'],
            'overcharge_amount' => $amount,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame($expected, $memo->deficiencies()->sole()->overcharge_amount);
        $this->assertSame($expected, AuditLog::query()->where('event', 'memo.created')->sole()->after_values['deficiencies'][0]['overcharge_amount']);
        $this->get(route('sdm-memos.edit', $memo))->assertOk()
            ->assertSee('value="'.$expected.'"', false);
        $this->get(route('sdm-memos.show', $memo))->assertOk()
            ->assertSee('Overcharged amount: ₹'.number_format((float) $amount, 2));
    }

    #[TestWith(['-1'])]
    #[TestWith(['not an amount'])]
    #[TestWith(['10000000000'])]
    #[TestWith(['1.234'])]
    public function test_invalid_overcharged_amount_cannot_be_saved(string $amount): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->from(route('sdm-memos.create'))->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'deficiencies' => ['overcharging'],
            'overcharge_amount' => $amount,
        ])->assertRedirect(route('sdm-memos.create'))->assertSessionHasErrors('overcharge_amount');

        $this->assertDatabaseCount('service_deficiency_memos', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'memo.created']);
        $this->get(route('sdm-memos.create'))->assertOk()->assertSee('value="'.$amount.'"', false);
    }

    public function test_overcharged_amount_is_retained_when_a_memo_is_submitted(): void
    {
        [$user] = $this->stationUserWithLine();
        $contractor = Contractor::query()->create(['name' => 'Ticketing Partner', 'is_active' => true]);

        $this->actingAs($user)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Ticketing->value,
            'contractor_id' => $contractor->id,
            'incident_date' => '18-08-2026',
            'incident_time' => '10:30',
            'staff_name' => 'Test Staff',
            'employee_number' => 'EMP-OVERCHARGE',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => ['overcharging'],
            'overcharge_amount' => '25.75',
        ])->assertRedirect(route('sdm-memos.index'))->assertSessionHasNoErrors();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame(MemoStatus::Submitted, $memo->status);
        $this->assertSame('25.75', $memo->deficiencies()->sole()->overcharge_amount);
        $this->assertSame('25.75', AuditLog::query()->where('event', 'memo.submitted')->sole()->after_values['deficiencies'][0]['overcharge_amount']);
    }

    public function test_overcharged_amount_can_be_edited_cleared_and_removed_with_its_deficiency(): void
    {
        [$user] = $this->stationUserWithLine();
        $data = ['service_type' => SdmType::Ticketing->value, 'deficiencies' => ['overcharging']];
        $this->actingAs($user)->post(route('sdm-memos.store'), [...$data, 'overcharge_amount' => '25.50'])->assertSessionHasNoErrors();
        $memo = ServiceDeficiencyMemo::query()->sole();

        $this->post(route('sdm-memos.update', $memo), [...$data, 'overcharge_amount' => '50.75'])->assertSessionHasNoErrors();
        $this->assertSame('50.75', $memo->deficiencies()->sole()->overcharge_amount);
        $this->post(route('sdm-memos.update', $memo), $data)->assertSessionHasNoErrors();
        $this->assertSame('50.75', $memo->deficiencies()->sole()->overcharge_amount);
        $this->post(route('sdm-memos.update', $memo), [...$data, 'overcharge_amount' => ''])->assertSessionHasNoErrors();
        $this->assertNull($memo->deficiencies()->sole()->overcharge_amount);
        $this->post(route('sdm-memos.update', $memo), [
            'service_type' => SdmType::Ticketing->value, 'deficiencies' => ['overcharging', 'other'], 'overcharge_amount' => '25',
        ])->assertSessionHasNoErrors();
        $this->assertNull($memo->deficiencies()->where('type', 'other')->sole()->overcharge_amount);
        $this->post(route('sdm-memos.update', $memo), [
            'service_type' => SdmType::Ticketing->value, 'deficiencies' => ['other'], 'overcharge_amount' => 'invalid hidden value',
        ])->assertSessionHasNoErrors();
        $this->assertSame(DeficiencyType::Other, $memo->deficiencies()->sole()->type);
        $this->assertNull($memo->deficiencies()->sole()->overcharge_amount);
    }

    public function test_late_reporting_uses_custom_times_and_displays_the_calculated_delay(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertDontSee('Morning shift')
            ->assertDontSee('Evening shift')
            ->assertDontSee('name="late_reporting_shift_preset"', false)
            ->assertSee('x-model="bookedShiftFrom"', false)
            ->assertSee('x-model="actualReportingTime"', false)
            ->assertSee('x-text="delayLabel"', false)
            ->assertSee('late_reporting[booked_shift_from]', false)
            ->assertSee('late_reporting[booked_shift_to]', false);
    }

    public function test_operator_phone_and_signature_upload_are_not_shown_or_required(): void
    {
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertDontSee('name="staff_mobile"', false)
            ->assertDontSee('name="staff_signature"', false);
    }

    public function test_cash_check_date_cannot_be_after_the_current_date(): void
    {
        $this->travelTo(Carbon::parse('2026-08-18 10:00:00'));
        [$user] = $this->stationUserWithLine();

        $this->actingAs($user)->post(route('sdm-memos.store'), [
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TV-FUTURE-CASH-CHECK',
            'deficiencies' => [DeficiencyType::CashCheck->value],
            'cash_check' => [
                'check_date' => '2026-08-19',
                'check_time' => '12:30',
            ],
        ])->assertSessionHasErrors('cash_check.check_date');

        $this->assertDatabaseMissing('service_deficiency_memos', [
            'serial_number' => 'TV-FUTURE-CASH-CHECK',
        ]);
    }

    public function test_serial_number_is_visible_in_the_owners_submitted_list_and_to_authorized_viewers(): void
    {
        [$owner] = $this->stationUserWithLine('RED');
        [$other] = $this->stationUserWithLine('GREEN');
        $viewerWithoutMemoPermissions = User::factory()->create();
        $memo = $this->draft($owner, SdmType::Cfs, 'SAME-1');

        $memo->update(['status' => MemoStatus::Submitted->value, 'submitted_at' => now()]);
        $this->actingAs($owner)->get(route('sdm-memos.index'))->assertOk()->assertSee($memo->serial_number);
        $this->actingAs($other)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);
        $this->actingAs($other)->get(route('sdm-memos.show', $memo))->assertForbidden();
        $this->actingAs($other)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('dashboard'))->assertOk()->assertSee('All memos');
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('sdm-memos.index'))->assertOk()->assertDontSee($memo->serial_number);
        $this->actingAs($viewerWithoutMemoPermissions)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSeeInOrder(['SDM Serial Number:', $memo->serial_number]);
    }

    public function test_draft_is_visible_only_to_the_user_who_created_it(): void
    {
        [$creator] = $this->stationUserWithLine('DRAFT-OWNER');
        $draft = $this->draft($creator, SdmType::Cfs, 'PRIVATE-DRAFT-101');
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->doesntContain('id', $draft->id))
            ->assertDontSee($draft->serial_number);

        $this->actingAs($administrator)
            ->get(route('sdm-memos.show', $draft))
            ->assertForbidden();

        $this->actingAs($creator)
            ->get(route('sdm-memos.edit', $draft))
            ->assertOk();
    }

    public function test_memo_index_can_filter_by_type_station_line_incident_date_and_status(): void
    {
        [$user, $targetLine, $targetStation] = $this->stationUserWithLine('FILTER-RED');
        [$otherUser] = $this->stationUserWithLine('FILTER-GREEN');
        $viewer = User::factory()->create();
        $target = $this->draft($user, SdmType::Cfs, 'FILTER-TARGET-101');
        $target->update([
            'incident_date' => '2026-09-10',
            'status' => MemoStatus::Submitted->value,
        ]);
        $other = $this->draft($otherUser, SdmType::Ticketing, 'OTHER-MEMO-202');
        $other->update(['incident_date' => '2026-09-11']);

        $this->actingAs($viewer)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('name="service_type"', false)
            ->assertDontSee('name="serial_number"', false)
            ->assertSee('name="station_id"', false)
            ->assertSee('name="line_code"', false)
            ->assertSee('name="incident_date"', false)
            ->assertSee('name="status"', false);

        $filters = [
            ['service_type' => SdmType::Cfs->value],
            ['station_id' => $targetStation->id],
            ['line_code' => $targetLine->code],
            ['incident_date' => '2026-09-10'],
            ['status' => MemoStatus::Submitted->value],
        ];

        foreach ($filters as $filter) {
            $this->actingAs($viewer)->get(route('sdm-memos.index', $filter))
                ->assertOk()
                ->assertViewHas('memos', fn ($memos): bool => collect($memos->items())->pluck('id')->all() === [$target->id])
                ->assertDontSee($target->serial_number)
                ->assertDontSee($other->serial_number);
        }
    }

    public function test_administrator_can_manage_master_data_and_referenced_records_cannot_be_deleted(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)->post(route('lines.store'), ['code' => 'YELLOW', 'name' => 'Yellow Line', 'is_active' => '1'])->assertRedirect();
        $line = Line::query()->where('code', 'YELLOW')->sole();
        Station::query()->create(['code' => 'YS1', 'name' => 'Yellow Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->actingAs($administrator)->delete(route('lines.destroy', $line))->assertSessionHasErrors('line');
        $this->assertModelExists($line);
    }

    public function test_line_names_may_repeat_but_line_codes_must_remain_unique(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-1',
            'name' => 'Blue Line',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-2',
            'name' => 'Blue Line',
            'is_active' => '1',
        ])->assertRedirect()->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Line::query()->where('name', 'Blue Line')->count());

        $this->actingAs($administrator)->post(route('lines.store'), [
            'code' => 'BLUE-1',
            'name' => 'Another Blue Line',
            'is_active' => '1',
        ])->assertSessionHasErrors('code');
    }

    public function test_existing_stations_are_displayed_linewise(): void
    {
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $zuluCodeLine = Line::query()->create(['code' => 'ZULU', 'name' => 'Alpha Line', 'is_active' => true]);
        $alphaCodeLine = Line::query()->create(['code' => 'ALPHA', 'name' => 'Zulu Line', 'is_active' => true]);

        Station::query()->create(['code' => 'ZULU-02', 'name' => 'Zulu Second', 'line_id' => $zuluCodeLine->id, 'is_active' => true]);
        Station::query()->create(['code' => 'ALPHA-01', 'name' => 'Alpha First', 'line_id' => $alphaCodeLine->id, 'is_active' => true]);
        Station::query()->create(['code' => 'ZULU-01', 'name' => 'Zulu First', 'line_id' => $zuluCodeLine->id, 'is_active' => true]);

        $this->actingAs($administrator)->get(route('stations.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-line-code="ALPHA"',
                'Alpha First',
                'data-line-code="ZULU"',
                'Zulu First',
                'Zulu Second',
            ], false);
    }

    private function stationUser(): User
    {
        [$user] = $this->stationUserWithLine();

        return $user;
    }

    private function stationUserWithLine(string $code = 'RED'): array
    {
        $line = Line::query()->create(['code' => $code, 'name' => $code.' Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => $code.'-01', 'name' => $code.' Station', 'line_id' => $line->id, 'is_active' => true]);
        $user = User::factory()->create(['station_id' => $station->id]);
        $user->assignRole(UserRole::Station->value);
        $stationManager = User::factory()->create(['employee_number' => $code.'-SM-1']);
        $stationManager->assignRole(UserRole::StationManager->value);
        $lineManager = User::factory()->create(['employee_number' => $code.'-AM-1']);
        $lineManager->assignRole(UserRole::AssistantManager->value);
        if (! User::query()->role(UserRole::ManagerOperations->value)->exists()) {
            $operationsManager = User::factory()->create();
            $operationsManager->assignRole(UserRole::ManagerOperations->value);
        }

        if (! User::query()->role(UserRole::DeputyGeneralManager->value)->exists()) {
            $dgm = User::factory()->create();
            $dgm->assignRole(UserRole::DeputyGeneralManager->value);
        }

        return [$user, $line, $station];
    }

    private function draft(User $user, SdmType $type, string $serial): ServiceDeficiencyMemo
    {
        $this->actingAs($user)->post(route('sdm-memos.store'), ['service_type' => $type->value])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->whereBelongsTo($user, 'creator')->latest('id')->firstOrFail();
        $memo->update(['serial_number' => $serial]);

        return $memo;
    }
}
