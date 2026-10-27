<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoCancellationType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Services\MemoAssessmentService;
use App\Support\CashMismatchPenalty;
use App\Support\PenaltyClauseTemplates;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractPenaltyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_administrator_creates_publishes_and_revises_a_seeded_contract(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $contractor = Contractor::query()->create(['name' => 'CFA Partner', 'is_active' => true]);
        $line = Line::query()->create(['code' => 'CFA-LINE', 'name' => 'CFA Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'CFA-STATION', 'name' => 'CFA Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->actingAs($administrator)->post(route('contracts.store'), [
            'contractor_id' => $contractor->id,
            'line_ids' => [$line->id],
            'station_ids' => [$station->id],
            'service_type' => SdmType::Cfs->value,
            'contract_number' => 'CFA/2026/01',
            'title' => 'Customer Facilitation Contract',
            'effective_from' => '2026-04-01',
            'effective_to' => '2027-03-31',
        ])->assertRedirect();

        $contract = ServiceContract::query()->sole();
        $this->assertSame('draft', $contract->status);
        $this->assertSame($line->id, $contract->line_id);
        $this->assertSame($station->id, $contract->station_id);
        $this->assertNull($contract->cmc_staff_id);
        $this->assertSame(1, $contract->version);
        $this->assertCount(12, $contract->penaltyClauses);
        $this->assertSame('Poor dress code', $contract->penaltyClauses->first()->description);

        $this->actingAs($administrator)
            ->get(route('contracts.index'))
            ->assertOk()
            ->assertSee('Contract Tenure')
            ->assertSee('01-04-2026 – 31-03-2027')
            ->assertDontSee('Location')
            ->assertDontSee('CFA-STATION')
            ->assertDontSee('CFA-LINE');

        $this->actingAs($administrator)
            ->post(route('contracts.publish', $contract))
            ->assertRedirect(route('contracts.show', $contract));

        $this->assertSame('published', $contract->fresh()->status);

        $this->actingAs($administrator)
            ->post(route('contracts.revise', $contract))
            ->assertRedirect();

        $revision = ServiceContract::query()->where('version', 2)->sole();
        $this->assertSame($contract->contract_group, $revision->contract_group);
        $this->assertSame($contract->line_id, $revision->line_id);
        $this->assertSame($contract->station_id, $revision->station_id);
        $this->assertSame([$line->id], $revision->lines()->pluck('lines.id')->all());
        $this->assertSame([$station->id], $revision->stations()->pluck('stations.id')->all());
        $this->assertSame('draft', $revision->status);
        $this->assertCount(12, $revision->penaltyClauses);
    }

    public function test_administrator_maps_a_contract_to_multiple_lines_and_stations_when_creating_and_updating_it(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $contractor = Contractor::query()->create(['name' => 'Mapped Contract Partner', 'is_active' => true]);
        $redLine = Line::query()->create(['code' => 'RED-MAP', 'name' => 'Alpha Mapping Line', 'is_active' => true]);
        $blueLine = Line::query()->create(['code' => 'BLUE-MAP', 'name' => 'Zulu Mapping Line', 'is_active' => true]);
        $redStation = Station::query()->create(['code' => 'RED-MAP-01', 'name' => 'Red Mapping Station', 'line_id' => $redLine->id, 'is_active' => true]);
        $secondRedStation = Station::query()->create(['code' => 'RED-MAP-02', 'name' => 'Second Red Mapping Station', 'line_id' => $redLine->id, 'is_active' => true]);
        $blueStation = Station::query()->create(['code' => 'BLUE-MAP-01', 'name' => 'Blue Mapping Station', 'line_id' => $blueLine->id, 'is_active' => true]);

        $createResponse = $this->actingAs($administrator)
            ->get(route('contracts.create'))
            ->assertOk()
            ->assertSee('data-contract-location-selector', false)
            ->assertSee('name="line_ids[]"', false)
            ->assertSee('name="station_ids[]"', false)
            ->assertSee('x-data="contractForm"', false)
            ->assertSee('Select all stations')
            ->assertSee('Clear stations')
            ->assertSee('Choose one or more lines to see their stations.')
            ->assertSee('Red Mapping Station');

        $createResponse->assertSeeInOrder(['>BLUE-MAP</span>', '>RED-MAP</span>'], false);

        $contractData = [
            'contractor_id' => $contractor->id,
            'service_type' => SdmType::Cfs->value,
            'contract_number' => 'CFA/MAPPED/01',
            'title' => 'Mapped Customer Facilitation Contract',
            'effective_from' => '2026-10-01',
            'effective_to' => '2026-10-31',
        ];

        $this->actingAs($administrator)->post(route('contracts.store'), [
            ...$contractData,
            'line_ids' => [$redLine->id],
            'station_ids' => [$redStation->id, $blueStation->id],
        ])->assertSessionHasErrors('station_ids');

        $this->actingAs($administrator)->post(route('contracts.store'), [
            ...$contractData,
            'line_ids' => [$redLine->id, $blueLine->id],
            'station_ids' => [$redStation->id],
        ])->assertSessionHasErrors('line_ids');

        $this->actingAs($administrator)->post(route('contracts.store'), [
            ...$contractData,
            'line_ids' => [$redLine->id, $blueLine->id],
            'station_ids' => [$redStation->id, $secondRedStation->id, $blueStation->id],
        ])->assertRedirect();

        $contract = ServiceContract::query()->sole();
        $this->assertEqualsCanonicalizing([$redLine->id, $blueLine->id], $contract->lines()->pluck('lines.id')->all());
        $this->assertEqualsCanonicalizing([$redStation->id, $secondRedStation->id, $blueStation->id], $contract->stations()->pluck('stations.id')->all());

        $this->actingAs($administrator)
            ->get(route('contracts.show', $contract))
            ->assertOk()
            ->assertSee('Lines')
            ->assertSee('Stations')
            ->assertSee('Second Red Mapping Station')
            ->assertSee('Blue Mapping Station');

        $this->actingAs($administrator)
            ->get(route('contracts.edit', $contract))
            ->assertOk()
            ->assertSee('Alpha Mapping Line')
            ->assertSee('Red Mapping Station');

        $this->actingAs($administrator)->put(route('contracts.update', $contract), [
            ...$contractData,
            'line_ids' => [$blueLine->id],
            'station_ids' => [$blueStation->id],
        ])->assertRedirect(route('contracts.show', $contract));

        $contract->refresh();
        $this->assertSame([$blueLine->id], $contract->lines()->pluck('lines.id')->all());
        $this->assertSame([$blueStation->id], $contract->stations()->pluck('stations.id')->all());

        $this->actingAs($administrator)
            ->post(route('contracts.publish', $contract))
            ->assertRedirect(route('contracts.show', $contract));

        $this->actingAs($administrator)
            ->post(route('contracts.revise', $contract))
            ->assertRedirect();

        $revision = ServiceContract::query()->where('version', 2)->sole();
        $this->assertSame([$blueLine->id], $revision->lines()->pluck('lines.id')->all());
        $this->assertSame([$blueStation->id], $revision->stations()->pluck('stations.id')->all());
    }

    public function test_a_published_contract_matches_an_sdm_from_any_assigned_station(): void
    {
        [$stationUser] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Multi-station Partner', 'is_active' => true]);
        $primaryStation = Station::query()->findOrFail($stationUser->station_id);
        $secondStation = Station::query()->create([
            'code' => 'RED-02',
            'name' => 'Second Red Station',
            'line_id' => $primaryStation->line_id,
            'is_active' => true,
        ]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/MULTI-STATION');
        $contract->lines()->sync([$primaryStation->line_id]);
        $contract->stations()->sync([$primaryStation->id, $secondStation->id]);
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $stationUser->update(['station_id' => $secondStation->id]);

        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->assertSame($secondStation->id, $memo->station_id);
        $this->assertSame($contract->id, $memo->service_contract_id);
    }

    public function test_overlapping_published_contract_versions_are_rejected(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'TVS Partner', 'is_active' => true]);

        $existing = $this->contract($contractor, $cmcStaff, SdmType::Ticketing, 'TVS/01');
        $existing->update(['status' => 'published', 'published_at' => now()]);
        $overlap = $this->contract($contractor, $cmcStaff, SdmType::Ticketing, 'TVS/02');

        $this->actingAs($administrator)
            ->from(route('contracts.show', $overlap))
            ->followingRedirects()
            ->post(route('contracts.publish', $overlap))
            ->assertOk()
            ->assertSee('This contract overlaps another published contract for the same station, contractor, and service.');

        $this->assertSame('draft', $overlap->fresh()->status);
    }

    public function test_partially_overlapping_published_contract_versions_are_rejected(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Partial Overlap Partner', 'is_active' => true]);

        $existing = $this->contract($contractor, $cmcStaff, SdmType::Ticketing, 'TVS/PARTIAL/01');
        $existing->update([
            'effective_from' => '2026-09-01',
            'effective_to' => '2026-09-30',
            'status' => 'published',
            'published_at' => now(),
        ]);
        $overlap = $this->contract($contractor, $cmcStaff, SdmType::Ticketing, 'TVS/PARTIAL/02');
        $overlap->update([
            'effective_from' => '2026-08-15',
            'effective_to' => '2026-09-15',
        ]);

        $this->actingAs($administrator)
            ->post(route('contracts.publish', $overlap))
            ->assertSessionHasErrors('effective_from');

        $this->assertSame('draft', $overlap->fresh()->status);
    }

    public function test_new_sdm_follows_the_contract_penalty_workflow_through_deduction(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, , $cmcTeamLead] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Metro Services', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/01');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $clause = $contract->penaltyClauses()->where('serial_number', 1)->sole();
        $addedClause = $contract->penaltyClauses()->where('serial_number', 2)->sole();

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFA-100',
            'contractor_id' => $contractor->id,
            'incident_date' => '01-08-2026',
            'incident_time' => '15:30',
            'staff_name' => 'Outsourced Employee',
            'employee_number' => 'EMP-100',
            'station_manager_employee_number' => $stationManager->employee_number,
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::DressCode->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
        ])->assertRedirect();

        $memo = ServiceDeficiencyMemo::query()->sole();
        $this->assertSame($contract->id, $memo->service_contract_id);
        $this->assertSame(MemoStatus::AwaitingStationManager, $memo->status);
        $this->assertNull($memo->staffAcknowledgement);
        $this->assertDatabaseCount('reporting_relationships', 0);
        $this->assertNull($memo->workflowSteps()->where('stage', WorkflowStepType::LineReview->value)->value('assigned_to'));
        $this->assertSame($operationsManager->id, $memo->workflowSteps()->where('stage', WorkflowStepType::AmCmcToScrutiny->value)->value('assigned_to'));
        $this->assertSame($cmcTeamLead->id, $memo->workflowSteps()->where('stage', WorkflowStepType::CmcAssignment->value)->value('assigned_to'));
        $this->assertSame($dgm->id, $memo->workflowSteps()->where('stage', WorkflowStepType::DgmApproval->value)->value('assigned_to'));
        $this->assertSame([
            WorkflowStepType::StationManager,
            WorkflowStepType::LineReview,
            WorkflowStepType::AmCmcToScrutiny,
            WorkflowStepType::CmcAssignment,
            WorkflowStepType::CmcAssessment,
            WorkflowStepType::CmcVerification,
            WorkflowStepType::AmCmcToDgm,
            WorkflowStepType::DgmApproval,
        ], $memo->workflowSteps()->orderBy('sequence')->get()->pluck('stage')->all());

        $alternativeLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-ALTERNATIVE']);
        $inactiveLineManager = $this->user(UserRole::AssistantManager, [
            'employee_number' => 'AM-INACTIVE',
            'is_enabled' => false,
        ]);

        $stationManagerIndexResponse = $this->actingAs($stationManager)->get(route('sdm-memos.index'));

        $stationManagerIndexResponse
            ->assertOk()
            ->assertSee('value="drafts_own"', false)
            ->assertSee('Drafts (own)')
            ->assertSee('value="submitted_to_me"', false)
            ->assertSee('Submitted to me')
            ->assertSee('value="pending_at_sm"', false)
            ->assertSee('Pending (at SM level)')
            ->assertSee('value="submitted_to_am"', false)
            ->assertSee('Submitted to AM')
            ->assertSee('value="processed_by_am"', false)
            ->assertSee('Processed by AM')
            ->assertSee('value="cancelled_by_am"', false)
            ->assertSee('Cancelled by AM')
            ->assertSee('SDM serial number')
            ->assertSee('SDM type')
            ->assertSee('SDM deficiency')
            ->assertSee('Line / station')
            ->assertSee('Incident date')
            ->assertSee('Submitted on')
            ->assertSee('Current status')
            ->assertSee('Last updated on')
            ->assertSee('Pending (at SM level)')
            ->assertSee('Poor Dress Code / Failure to Follow Instructions')
            ->assertSee('01-08-2026 10:00');

        $this->assertDoesNotMatchRegularExpression('/>\s*Serial number\s*</', $stationManagerIndexResponse->getContent());
        $this->assertDoesNotMatchRegularExpression('/>\s*Action\s*</', $stationManagerIndexResponse->getContent());
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'submitted_to_me']))
            ->assertOk()
            ->assertSee($memo->serial_number);
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'pending_at_sm']))
            ->assertOk()
            ->assertSee($memo->serial_number);

        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertDontSee('Contract assessment')
            ->assertDontSee('Memo CMC Staff')
            ->assertSee('Station Manager review')
            ->assertDontSee('Assistant Manager/Manager(Line) review')
            ->assertDontSee('Manager CMC forwarding for scrutiny')
            ->assertDontSee('CMC SM- staff assignment')
            ->assertDontSee('CMC Staff scrutiny and assessment')
            ->assertDontSee('Manager CMC forwarding to DGM/CMC')
            ->assertDontSee('DGM/CMC approval');
        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertDontSee('Contract assessment')
            ->assertDontSee('Memo CMC Staff')
            ->assertSee('Station Manager review')
            ->assertSee('Assistant Manager/Manager(Line) review')
            ->assertDontSee('Manager CMC forwarding for scrutiny')
            ->assertDontSee('CMC SM- staff assignment')
            ->assertDontSee('CMC Staff scrutiny and assessment')
            ->assertDontSee('Manager CMC forwarding to DGM/CMC')
            ->assertDontSee('DGM/CMC approval')
            ->assertSee('Remarks / cancellation request to AM (optional)')
            ->assertDontSee('value="cancel"', false)
            ->assertDontSee('Cancel SDM')
            ->assertSee('&quot;label&quot;:&quot;Line&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Station&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;SDM type&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Selected Deficiency&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Incident date and time&quot;', false)
            ->assertSee('&quot;label&quot;:&quot;Submitted on&quot;', false)
            ->assertSee('data-confirmation-show-action="false"', false)
            ->assertSee('data-confirmation-fields="[]"', false)
            ->assertSee('01-08-2026 15:30')
            ->assertSee('Poor Dress Code / Failure to Follow Instructions')
            ->assertSee('name="forward_to_employee_number"', false)
            ->assertSee('x-data="employeeAutocomplete"', false)
            ->assertSee($lineManager->employee_number.' - '.$lineManager->name)
            ->assertSee($alternativeLineManager->employee_number.' - '.$alternativeLineManager->name)
            ->assertDontSee($inactiveLineManager->employee_number.' - '.$inactiveLineManager->name)
            ->assertSee('data-sdm-confirm', false)
            ->assertSee('data-confirmation-action="Forward SDM"', false)
            ->assertSee('Are you sure you want to proceed?');

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number])->assertRedirect();
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Forwarded to AM</span>', false);
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'submitted_to_am']))
            ->assertOk()
            ->assertSee($memo->serial_number);
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('SDM serial number')
            ->assertSee('SDM type')
            ->assertSee('SDM deficiency')
            ->assertSee('Line / station')
            ->assertSee('Incident date')
            ->assertSee('Submitted on')
            ->assertSee('Current status')
            ->assertSee('Last updated on')
            ->assertSee('Action')
            ->assertSee('01-08-2026')
            ->assertSee('01-08-2026 10:00')
            ->assertSee('name="action" value="cancel"', false)
            ->assertSee('Cancellation remarks');
        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Contract assessment')
            ->assertDontSee('Memo CMC Staff')
            ->assertSee("x-data='{ selectedClauses: [\"{$clause->id}\"], showAllClauses: false }'", false)
            ->assertSee('Penalties matching the recorded deficiencies are pre-selected. You may add or remove clauses before forwarding.')
            ->assertSee("x-show=\"showAllClauses || selectedClauses.includes('{$clause->id}')\"", false)
            ->assertSee("x-show=\"showAllClauses || selectedClauses.includes('{$addedClause->id}')\"", false)
            ->assertSee('x-show="! showAllClauses"', false)
            ->assertSee('@click="showAllClauses = true"', false)
            ->assertSee('Add more deficiency')
            ->assertSee('name="items[0][proposed_amount]"', false)
            ->assertSee('value="200.00"', false)
            ->assertSee('data-timeline-state="forwarded"', false)
            ->assertSee('data-timeline-state="current"', false)
            ->assertDontSee('Manager CMC forwarding for scrutiny')
            ->assertDontSee('DGM/CMC approval')
            ->assertSee('value="cancel"', false)
            ->assertSee('Cancel SDM')
            ->assertSee('name="action" value="cancel" data-sdm-confirm data-confirmation-action="Cancel SDM"', false)
            ->assertDontSee('R1 ·');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $addedClause->id,
                'proposed_amount' => '150.00',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Processed by AM</span>', false)
            ->assertDontSee('With Manager CMC forwarding for scrutiny');
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'processed_by_am']))
            ->assertOk()
            ->assertSee($memo->serial_number);
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('With Manager CMC forwarding for scrutiny')
            ->assertDontSee('>Forwarded By AM/Manager (Line)</span>', false);
        $lineAssessmentItem = $memo->assessments()->latest('version')->firstOrFail()->items()->sole();
        $this->assertSame($addedClause->id, $lineAssessmentItem->contract_penalty_clause_id);
        $this->assertNotSame($clause->id, $lineAssessmentItem->contract_penalty_clause_id);
        $this->assertNull($lineAssessmentItem->justification);
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect();
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign',
            'cmc_staff_id' => $cmcStaff->id,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cmcStaff)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee("x-data='{ selectedClauses: [\"{$addedClause->id}\"], showAllClauses: false }'", false)
            ->assertSee('Add more deficiencies')
            ->assertSee('name="items[1][proposed_amount]"', false)
            ->assertSee('value="150.00"', false)
            ->assertSee('name="proposed_total" type="number" min="0" step="0.01" value="150.00"', false)
            ->assertSee('name="negative_points" type="number" min="0" value="0"', false)
            ->assertDontSee('<option value="none">', false)
            ->assertSee('<option value="warning">Warning</option>', false)
            ->assertSee('<option value="final_warning">Final Warning</option>', false)
            ->assertSee('<option value="recall">Recall</option>', false)
            ->assertDontSee('name="bill_month"', false)
            ->assertDontSee('name="monthly_bill_value"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="assessment_pdf"', false)
            ->assertSee('accept=".pdf,application/pdf"', false)
            ->assertDontSee('Points justification')
            ->assertDontSee('Staff-action justification')
            ->assertDontSee('name="points_justification"', false)
            ->assertDontSee('name="action_justification"', false);
        $this->actingAs($cmcStaff)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $addedClause->id,
                'proposed_amount' => '200.00',
            ]],
            'proposed_total' => '175.00',
            'negative_points' => 2,
            'staff_action' => 'warning',
            'assessment_pdf' => UploadedFile::fake()->create('cmc-staff-assessment.pdf', 50, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();
        $cmcAssessment = $memo->assessments()
            ->where('source_stage', WorkflowStepType::CmcAssessment->value)
            ->sole();
        $this->assertSame(WorkflowStepType::CmcAssessment->value, $cmcAssessment->source_stage);
        $this->assertNull($cmcAssessment->bill_month);
        $this->assertNull($cmcAssessment->monthly_bill_value);
        $this->assertSame('175.00', $cmcAssessment->proposed_total);
        $this->assertNull($cmcAssessment->items()->sole()->justification);
        $cmcStaffAttachment = $memo->attachments()->where('original_name', 'cmc-staff-assessment.pdf')->sole();
        $this->assertSame($cmcStaff->id, $cmcStaffAttachment->uploaded_by);
        Storage::disk('local')->assertExists($cmcStaffAttachment->storage_path);
        $this->actingAs($cmcStaff)
            ->get(route('sdm-memos.attachments.download', [$memo, $cmcStaffAttachment]))
            ->assertOk();
        $this->actingAs($cmcStaff)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('AM/Manager (Line) Review 1')
            ->assertSee('CMC Review 1')
            ->assertDontSee('Proposal v1')
            ->assertDontSee('round 1');
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::PendingPenaltyLetter, $memo->status);
        $this->assertSame('175.00', $memo->assessments()->where('status', 'approved')->sole()->proposed_total);
        $otherCmcStaff = $this->user(UserRole::CmcStaff);
        $this->actingAs($otherCmcStaff)->post(route('sdm-memos.penalty-letter.store', $memo), [
            'issued_at' => '2026-08-01',
            'delivery_details' => 'This user is not assigned to the memo.',
            'letter' => UploadedFile::fake()->create('unauthorized-letter.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($cmcStaff)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Contract assessment')
            ->assertSee('Penalty letter, objection, and deduction');

        $this->actingAs($cmcStaff)->post(route('sdm-memos.penalty-letter.store', $memo), [
            'issued_at' => '2026-08-01',
            'delivery_details' => 'Delivered by registered post.',
            'letter' => UploadedFile::fake()->create('signed-letter.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(MemoStatus::ObjectionWindow, $memo->status);
        $this->assertSame('2026-08-16', $memo->currentPenaltyLetter->objection_due_at->toDateString());

        $firstLetter = $memo->currentPenaltyLetter;
        $this->actingAs($stationUser)->get(route('sdm-memos.penalty-letter.download', [$memo, $firstLetter]))->assertOk();
        $this->actingAs($cmcStaff)->post(route('sdm-memos.objection.store', $memo), [
            'received_at' => '2026-08-10',
            'remarks' => 'Contractor disputed the first assessment.',
            'attachment' => UploadedFile::fake()->create('objection.pdf', 25, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(MemoStatus::ObjectionReview, $memo->fresh()->status);
        $this->actingAs($cmcStaff)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $addedClause->id,
                'proposed_amount' => '180.00',
                'justification' => 'Revised after considering the objection.',
            ]],
            'proposed_total' => '165.00',
            'negative_points' => 1,
            'staff_action' => 'warning',
            'bill_month' => '2026-08',
            'monthly_bill_value' => '10000.00',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($cmcStaff)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('CMC Review 2')
            ->assertDontSee('Proposal v3')
            ->assertDontSee('round 2');
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(MemoStatus::PendingPenaltyLetter, $memo->fresh()->status);
        $this->assertSame(['proposed', 'superseded', 'approved'], $memo->assessments()->orderBy('version')->pluck('status')->all());
        $this->assertSame('165.00', $memo->assessments()->where('status', 'approved')->sole()->proposed_total);
        $this->actingAs($cmcStaff)->post(route('sdm-memos.penalty-letter.store', $memo), [
            'issued_at' => '2026-08-12',
            'delivery_details' => 'Revised letter delivered by registered post.',
            'letter' => UploadedFile::fake()->create('revised-signed-letter.pdf', 50, 'application/pdf'),
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(2, $memo->currentPenaltyLetter->revision);
        $this->assertSame('2026-08-27', $memo->currentPenaltyLetter->objection_due_at->toDateString());

        Carbon::setTestNow('2026-08-28 10:00:00');
        $this->actingAs($cmcStaff)
            ->post(route('sdm-memos.no-objection.store', $memo))
            ->assertRedirect();

        $this->actingAs($cmcStaff)->post(route('sdm-memos.deduction.store', $memo), [
            'bill_month' => '2026-08',
            'bill_reference' => 'BILL-2026-08-19',
            'deducted_at' => '2026-08-28',
            'amount' => '165.00',
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(MemoStatus::Closed, $memo->status);
        $this->assertSame('165.00', $memo->deduction->amount);
        $this->assertGreaterThanOrEqual(15, $memo->auditLogs()->count());
    }

    public function test_dgm_return_preserves_the_first_round_and_routes_back_to_cmc(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, , $cmcTeamLead] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Return Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/RETURN');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $clause = $contract->penaltyClauses()->firstOrFail();
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number]);
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'items' => [[
            'contract_penalty_clause_id' => $clause->id,
            'proposed_amount' => 100,
            'justification' => 'Initial proposal.',
        ]]]);
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward']);
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign',
            'cmc_staff_id' => $cmcStaff->id,
        ]);
        $this->actingAs($cmcStaff)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => 100,
                'justification' => 'CMC proposal.',
            ]],
            'proposed_total' => 100,
            'negative_points' => 1,
            'staff_action' => 'warning',
            'bill_month' => '2026-08',
            'monthly_bill_value' => 10000,
        ]);
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward']);
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward']);

        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'Reassess the proposed amount.',
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(MemoStatus::CmcScrutiny, $memo->status);
        $this->assertSame(2, $memo->currentWorkflowStep->round);
        $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
        $this->assertSame(12, $memo->workflowSteps()->count());
        $this->assertSame('returned', $memo->workflowSteps()->where('stage', WorkflowStepType::DgmApproval->value)->where('round', 1)->value('decision'));
        $this->assertSame($cmcStaff->id, $memo->cmc_staff_id);
        $this->assertSame($cmcStaff->id, $memo->currentWorkflowStep->assigned_to);
    }

    public function test_am_cmc_can_revert_an_sdm_to_the_previous_sm_cmc_hierarchy(): void
    {
        [$stationUser, , , $operationsManager, , , $cmcTeamLead] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'AM CMC Return Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/AM-CMC-RETURN');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $amCmcStep = $memo->workflowSteps()->where('stage', WorkflowStepType::AmCmcToDgm->value)->sole();

        $memo->workflowSteps()->where('sequence', '<', $amCmcStep->sequence)->update([
            'decision' => 'forwarded',
            'acted_at' => now(),
        ]);
        $memo->update([
            'status' => MemoStatus::AmCmcReview->value,
            'current_workflow_step_id' => $amCmcStep->id,
        ]);

        $this->actingAs($operationsManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Remarks (mandatory when reverting for rechecking)')
            ->assertSee('name="action" value="return"', false)
            ->assertSee('Revert to SM/CMC for rechecking');

        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
        ])->assertSessionHasErrors('remarks');

        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'SM/CMC must verify the supporting calculation again.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::CmcScrutiny, $memo->status);
        $this->assertSame(WorkflowStepType::CmcVerification, $memo->currentWorkflowStep->stage);
        $this->assertSame($cmcTeamLead->id, $memo->currentWorkflowStep->assigned_to);
        $this->assertSame(2, $memo->currentWorkflowStep->round);
        $this->assertSame(11, $memo->workflowSteps()->count());
        $this->assertSame('returned', $amCmcStep->fresh()->decision);
        $this->assertSame('SM/CMC must verify the supporting calculation again.', $amCmcStep->fresh()->remarks);

        $this->actingAs($cmcTeamLead)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('SM/CMC must verify the supporting calculation again.')
            ->assertSee(WorkflowStepType::CmcVerification->label());
    }

    public function test_contract_workflow_station_manager_cannot_cancel_directly(): void
    {
        [$stationUser, $stationManager] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'SM Cancellation Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/SM-CANCEL');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled by the Station Manager.',
        ])->assertSessionHasErrors('action');

        $this->assertSame(MemoStatus::AwaitingStationManager, $memo->fresh()->status);
        $this->assertSame(WorkflowStepType::StationManager, $memo->fresh()->currentWorkflowStep->stage);
    }

    public function test_station_manager_can_request_cancellation_from_am_without_remarks(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Cancellation Request Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/CANCEL-REQUEST');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Remarks / cancellation request to AM (optional)')
            ->assertSee('name="action" value="request_cancellation"', false)
            ->assertSee('Request for cancellation');

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'request_cancellation',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $stationManagerStep = $memo->workflowSteps()->where('stage', WorkflowStepType::StationManager->value)->sole();

        $this->assertSame(MemoStatus::LineReview, $memo->status);
        $this->assertSame($lineManager->id, $memo->currentWorkflowStep->assigned_to);
        $this->assertSame('forwarded', $stationManagerStep->decision);
        $this->assertSame('Cancellation requested by Station Manager.', $stationManagerStep->remarks);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $memo->id,
            'event' => 'memo.workflow.request_cancellation',
        ]);

        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'request_cancellation',
        ])->assertSessionHasErrors('action');

        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Cancellation requested by Station Manager.');

        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Cancellation requested</span>', false);
    }

    public function test_contract_workflow_am_line_can_cancel_after_counselling_with_mandatory_remarks(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'AM Cancellation Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/AM-CANCEL');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))
            ->assertOk()
            ->assertSee('Save changes')
            ->assertDontSee('Submit memo');
        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();
        $this->actingAs($stationUser)->get(route('sdm-memos.edit', $memo))->assertForbidden();
        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('name="action" value="cancel"', false)
            ->assertSee('Cancel SDM')
            ->assertSee('name="action" value="cancel_after_counselling"', false)
            ->assertSee('Cancel after counselling');
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('Cancel after counselling');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel_after_counselling'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel_after_counselling',
            'remarks' => 'Counselling completed with the staff member.',
        ])->assertRedirect(route('sdm-memos.index'));

        $this->assertSame(MemoStatus::Cancelled, $memo->fresh()->status);
        $this->assertSame(MemoCancellationType::AfterCounselling, $memo->fresh()->cancellation_type);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $memo->id,
            'event' => 'memo.workflow.cancel_after_counselling',
        ]);
        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Cancelled after counselling by AM/Line');
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Cancelled after counselling by AM/Line</span>', false);
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'cancelled_by_am']))
            ->assertOk()
            ->assertSee($memo->serial_number);
    }

    public function test_contract_workflow_am_line_cancel_is_separate_from_cancel_after_counselling(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'AM Direct Cancellation Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/AM-DIRECT-CANCEL');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();

        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled without counselling.',
        ])->assertRedirect(route('sdm-memos.index'))
            ->assertSessionHas('status', 'SDM cancelled.');

        $memo->refresh();
        $this->assertSame(MemoStatus::Cancelled, $memo->status);
        $this->assertSame(MemoCancellationType::Cancelled, $memo->cancellation_type);
        $this->assertSame('Cancelled by AM/Line', $memo->cancellationLabel());
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $memo->id,
            'event' => 'memo.workflow.cancel',
        ]);
        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Cancelled by AM/Line')
            ->assertDontSee('Cancelled after counselling by AM/Line');
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Cancelled by AM/Line</span>', false)
            ->assertDontSee('>Cancelled after counselling by AM/Line</span>', false);
    }

    public function test_case_timeline_starts_with_the_sdm_creator_and_creation_time(): void
    {
        [$stationUser, $stationManager] = $this->workflowUsers();
        $stationUser->update(['name' => 'Initiator <SC>', 'employee_number' => 'SC-INITIATOR']);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Creator Timeline Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/CREATOR-TIMELINE');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $memo->forceFill(['created_at' => '2026-10-20 09:15:00', 'submitted_at' => '2026-10-21 10:30:00'])->save();

        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Case timeline',
                'SDM created',
                'Created by Initiator <SC>',
                'SC-INITIATOR',
                '20-10-2026 09:15',
                WorkflowStepType::StationManager->label(),
            ])
            ->assertDontSee('Initiator <SC>', false);
    }

    public function test_case_timeline_reveals_only_completed_and_current_steps_to_workflow_users(): void
    {
        [$stationUser, , $lineManager, $operationsManager, $dgm, , $cmcTeamLead] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Timeline Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/TIMELINE');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $steps = $memo->workflowSteps()->get()->keyBy(fn ($step): string => $step->stage->value);

        $this->actingAs($administrator)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('<details data-case-timeline data-default-collapsed="true"', false)
            ->assertDontSee('data-case-timeline open', false)
            ->assertSeeTextInOrder(['Case timeline', 'SDM created', $stationUser->name, WorkflowStepType::StationManager->label()])
            ->assertSee(WorkflowStepType::DgmApproval->label());

        $cases = [
            [$lineManager, WorkflowStepType::LineReview, WorkflowStepType::AmCmcToScrutiny],
            [$operationsManager, WorkflowStepType::AmCmcToScrutiny, WorkflowStepType::CmcAssignment],
            [$cmcTeamLead, WorkflowStepType::CmcAssignment, WorkflowStepType::CmcAssessment],
            [$cmcStaff, WorkflowStepType::CmcAssessment, WorkflowStepType::CmcVerification],
            [$cmcTeamLead, WorkflowStepType::CmcVerification, WorkflowStepType::AmCmcToDgm],
            [$operationsManager, WorkflowStepType::AmCmcToDgm, WorkflowStepType::DgmApproval],
            [$dgm, WorkflowStepType::DgmApproval, null],
        ];

        foreach ($cases as [$viewer, $currentStage, $hiddenFutureStage]) {
            $currentStep = $steps->get($currentStage->value);
            $memo->workflowSteps()->update(['decision' => 'pending', 'acted_at' => null]);
            $memo->workflowSteps()->where('sequence', '<', $currentStep->sequence)->update([
                'decision' => 'forwarded',
                'acted_at' => now(),
            ]);
            $memo->update(['current_workflow_step_id' => $currentStep->id]);

            $response = $this->actingAs($viewer)->get(route('sdm-memos.show', $memo))
                ->assertOk()
                ->assertSee('<details data-case-timeline data-default-collapsed="true"', false)
                ->assertDontSee('data-case-timeline open', false)
                ->assertSeeTextInOrder(['Case timeline', 'SDM created', $stationUser->name, WorkflowStepType::StationManager->label()])
                ->assertSee($currentStage->label());

            if ($hiddenFutureStage !== null) {
                $response->assertDontSee($hiddenFutureStage->label());
            }
        }
    }

    public function test_cancelled_case_timeline_stops_at_the_cancelled_stage_even_for_administrators(): void
    {
        [$stationUser] = $this->workflowUsers();
        $administrator = $this->user(UserRole::Administrator);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Cancelled Timeline Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/CANCELLED-TIMELINE');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $lineStep = $memo->workflowSteps()->where('stage', WorkflowStepType::LineReview->value)->sole();

        $memo->workflowSteps()->where('sequence', '<', $lineStep->sequence)->update([
            'decision' => 'forwarded',
            'acted_at' => now(),
        ]);
        $lineStep->update(['decision' => 'cancelled', 'acted_at' => now()]);
        $memo->update([
            'status' => MemoStatus::Cancelled->value,
            'current_workflow_step_id' => null,
        ]);

        $this->actingAs($administrator)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee(WorkflowStepType::LineReview->label())
            ->assertSee('data-timeline-state="cancelled"', false)
            ->assertDontSee(WorkflowStepType::AmCmcToScrutiny->label())
            ->assertDontSee(WorkflowStepType::DgmApproval->label());
    }

    public function test_cmc_team_lead_assigns_and_explicitly_reassigns_one_staff_member_for_the_memo_lifecycle(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, , , $cmcTeamLead] = $this->workflowUsers();
        $contractStaff = $this->user(UserRole::CmcStaff, ['name' => 'Contract Staff']);
        $assignedStaff = $this->user(UserRole::CmcStaff, ['name' => 'Assigned Staff']);
        $replacementStaff = $this->user(UserRole::CmcStaff, ['name' => 'Replacement Staff']);
        $nonCmcUser = $this->user(UserRole::AssistantManager, ['name' => 'Not CMC Staff']);
        $contractor = Contractor::query()->create(['name' => 'Assignment Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $contractStaff, SdmType::Cfs, 'CFA/ASSIGNMENT');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $clause = $contract->penaltyClauses()->firstOrFail();
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'forward_to_employee_number' => $lineManager->employee_number]);
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward', 'items' => [[
            'contract_penalty_clause_id' => $clause->id,
            'proposed_amount' => 100,
            'justification' => 'Initial proposal.',
        ]]]);
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward']);

        $memo->refresh();
        $this->assertSame(WorkflowStepType::CmcAssignment, $memo->currentWorkflowStep->stage);
        $this->assertSame($cmcTeamLead->id, $memo->currentWorkflowStep->assigned_to);
        $this->assertNull($memo->cmc_staff_id);

        $this->actingAs($cmcTeamLead)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Assign CMC Staff')
            ->assertSee('Assigned Staff')
            ->assertSee('Replacement Staff')
            ->assertDontSee('Not CMC Staff');

        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign',
            'cmc_staff_id' => $assignedStaff->id,
        ])->assertForbidden();

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign',
            'cmc_staff_id' => $nonCmcUser->id,
        ])->assertSessionHasErrors('cmc_staff_id');

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'assign',
            'cmc_staff_id' => $assignedStaff->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame($assignedStaff->id, $memo->cmc_staff_id);
        $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
        $this->assertSame($assignedStaff->id, $memo->currentWorkflowStep->assigned_to);
        $this->actingAs($replacementStaff)->get(route('sdm-memos.show', $memo))->assertOk()->assertSee($memo->serial_number);

        $this->actingAs($cmcTeamLead)->patch(route('sdm-memos.cmc-assignment.update', $memo), [
            'cmc_staff_id' => $replacementStaff->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame($replacementStaff->id, $memo->cmc_staff_id);
        $this->assertSame($replacementStaff->id, $memo->currentWorkflowStep->assigned_to);
        $this->actingAs($assignedStaff)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
        ])->assertForbidden();
        $this->actingAs($replacementStaff)->get(route('sdm-memos.show', $memo))->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $memo->id,
            'event' => 'memo.cmc_staff.reassigned',
            'actor_id' => $cmcTeamLead->id,
        ]);
    }

    public function test_cmc_team_lead_can_choose_direct_verification_and_forward_the_sdm_to_am_cmc(): void
    {
        [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, , $cmcTeamLead] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff, ['name' => 'Assigned CMC Staff']);
        $contractor = Contractor::query()->create(['name' => 'SM CMC Verification Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/SM-CMC-VERIFY');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $clause = $contract->penaltyClauses()->firstOrFail();
        $additionalClause = $contract->penaltyClauses()->where('id', '!=', $clause->id)->firstOrFail();
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $memo->deficiencies()->update(['type' => DeficiencyType::BreachOfInstructions->value]);

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect();
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => '100.00',
            ]],
        ])->assertRedirect();
        $this->actingAs($operationsManager)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertRedirect();

        $memo->refresh();
        $this->assertSame(WorkflowStepType::CmcAssignment, $memo->currentWorkflowStep->stage);
        $this->assertNull($memo->cmc_staff_id);

        $this->actingAs($cmcTeamLead)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Assign for scrutiny')
            ->assertSee('Verify this SDM directly');

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'verify',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
        $this->assertSame($cmcTeamLead->id, $memo->currentWorkflowStep->assigned_to);
        $this->assertNull($memo->cmc_staff_id);

        $this->actingAs($cmcTeamLead)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('Verify and forward')
            ->assertSee("x-data='{ selectedClauses: [\"{$clause->id}\"], showAllClauses: false }'", false)
            ->assertSee("x-show=\"showAllClauses || selectedClauses.includes('{$clause->id}')\"", false)
            ->assertSee("x-show=\"showAllClauses || selectedClauses.includes('{$additionalClause->id}')\"", false)
            ->assertSee('Add more deficiencies')
            ->assertSee('name="proposed_total" type="number" min="0" step="0.01" value="100.00"', false)
            ->assertSee('name="negative_points" type="number" min="0" value="3"', false)
            ->assertDontSee('<option value="none">', false)
            ->assertSee('<option value="warning">Warning</option>', false)
            ->assertSee('<option value="final_warning">Final Warning</option>', false)
            ->assertSee('<option value="recall">Recall</option>', false)
            ->assertDontSee('name="bill_month"', false)
            ->assertDontSee('name="monthly_bill_value"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="assessment_pdf"', false)
            ->assertSee('accept=".pdf,application/pdf"', false)
            ->assertSee('name="staff_action"', false);

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => '125.00',
            ]],
            'negative_points' => 1,
            'staff_action' => 'warning',
        ])->assertSessionHasErrors('proposed_total');

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => '125.00',
            ]],
            'proposed_total' => '110.00',
            'negative_points' => 1,
            'staff_action' => 'none',
            'bill_month' => now()->format('Y-m'),
            'monthly_bill_value' => '10000.00',
        ])->assertSessionHasErrors('staff_action');

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => '125.00',
            ]],
            'proposed_total' => '110.00',
            'negative_points' => 1,
            'staff_action' => 'warning',
            'assessment_pdf' => UploadedFile::fake()->create('not-a-pdf.jpg', 10, 'image/jpeg'),
        ])->assertSessionHasErrors('assessment_pdf');

        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $clause->id,
                'proposed_amount' => '125.00',
                'justification' => 'Verified directly by SM/CMC.',
            ]],
            'proposed_total' => '110.00',
            'negative_points' => 1,
            'staff_action' => 'warning',
            'assessment_pdf' => UploadedFile::fake()->create('sm-cmc-assessment.pdf', 50, 'application/pdf'),
            'remarks' => 'SM/CMC verification completed.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::CmcScrutiny, $memo->status);
        $this->assertSame(WorkflowStepType::CmcVerification, $memo->currentWorkflowStep->stage);
        $this->actingAs($cmcTeamLead)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'remarks' => 'SM/CMC verification completed.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::AmCmcReview, $memo->status);
        $this->assertSame(WorkflowStepType::AmCmcToDgm, $memo->currentWorkflowStep->stage);
        $this->assertSame($operationsManager->id, $memo->currentWorkflowStep->assigned_to);

        $assessment = $memo->assessments()
            ->where('source_stage', WorkflowStepType::CmcAssessment->value)
            ->sole();
        $this->assertSame($cmcTeamLead->id, $assessment->proposed_by);
        $this->assertSame('110.00', $assessment->proposed_total);
        $this->assertNull($assessment->bill_month);
        $this->assertNull($assessment->monthly_bill_value);
        $teamLeadAttachment = $memo->attachments()->where('original_name', 'sm-cmc-assessment.pdf')->sole();
        $this->assertSame($cmcTeamLead->id, $teamLeadAttachment->uploaded_by);
        Storage::disk('local')->assertExists($teamLeadAttachment->storage_path);
        $assessmentAudit = $memo->auditLogs()
            ->where('event', 'memo.assessment.created')
            ->where('actor_id', $cmcTeamLead->id)
            ->sole();
        $this->assertSame($teamLeadAttachment->id, $assessmentAudit->after_values['assessment_pdf_attachment_id']);
        $this->actingAs($cmcTeamLead)
            ->get(route('sdm-memos.attachments.download', [$memo, $teamLeadAttachment]))
            ->assertOk();
        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $memo->id,
            'event' => 'memo.workflow.forward',
            'actor_id' => $cmcTeamLead->id,
        ]);

        $this->actingAs($operationsManager)
            ->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])
            ->assertRedirect();
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'SM/CMC must reassess the proposal.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::CmcScrutiny, $memo->status);
        $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
        $this->assertSame($cmcTeamLead->id, $memo->currentWorkflowStep->assigned_to);
        $this->actingAs($cmcTeamLead)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('name="negative_points" type="number" min="0" value="1"', false);
    }

    public function test_negative_points_prefill_uses_the_maximum_across_deficiencies_and_cash_check_details(): void
    {
        [$stationUser] = $this->workflowUsers();
        $contractor = Contractor::query()->create(['name' => 'Negative Points Partner', 'is_active' => true]);
        $station = Station::query()->findOrFail($stationUser->station_id);
        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TVS-NEGATIVE-POINTS',
            'station_id' => $station->id,
            'station_name' => $station->name,
            'contractor_id' => $contractor->id,
            'contractor_name' => $contractor->name,
            'created_by' => $stationUser->id,
        ]);

        $memo->deficiencies()->createMany([
            ['type' => DeficiencyType::BreachOfInstructions->value],
            ['type' => DeficiencyType::PublicComplaint->value],
            ['type' => DeficiencyType::CashCheck->value],
        ]);
        $memo->cashCheck()->create([
            'difference_amount' => '-75.00',
            'hidden_cash_amount' => '25.00',
        ]);

        $memo->load(['deficiencies', 'cashCheck']);

        $this->assertSame(10, app(MemoAssessmentService::class)->negativePointsPrefill($memo));
    }

    public function test_late_reporting_penalty_is_suggested_from_the_recorded_delay_and_can_be_edited(): void
    {
        [$stationUser, $stationManager, $lineManager] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Late Reporting Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/LATE-REPORTING');
        $contract->update(['status' => 'published', 'published_at' => now()]);
        $memo = $this->submittedMemo($stationUser, $contractor, $contract);
        $deficiency = $memo->deficiencies()->sole();
        $deficiency->update(['type' => DeficiencyType::LateReporting->value]);
        $deficiency->lateReporting()->create([
            'booked_shift_from' => '09:00',
            'booked_shift_to' => '17:00',
            'actual_reporting_time' => '09:31',
        ]);
        $lateReportingClause = $contract->penaltyClauses()->where('serial_number', 4)->sole();

        $this->actingAs($stationManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'forward_to_employee_number' => $lineManager->employee_number,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->actingAs($lineManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('name="items[3][proposed_amount]"', false)
            ->assertSee('value="200.00"', false);

        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'forward',
            'items' => [[
                'contract_penalty_clause_id' => $lateReportingClause->id,
                'proposed_amount' => '100.00',
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $assessmentItem = $memo->assessments()->sole()->items()->sole();
        $this->assertSame('200.00', $assessmentItem->suggested_amount);
        $this->assertSame('100.00', $assessmentItem->proposed_amount);
    }

    public function test_tvs_template_and_contract_calculations_are_snapshotted(): void
    {
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'TVS Calculation Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Ticketing, 'TVS/CALC');
        $this->assertCount(18, $contract->penaltyClauses);

        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TVS-CALC-1',
            'contractor_id' => $contractor->id,
            'service_contract_id' => $contract->id,
            'contractor_name' => $contractor->name,
            'workflow_version' => 2,
            'status' => MemoStatus::CmcScrutiny->value,
            'created_by' => $cmcStaff->id,
            'station_id' => Station::query()->create([
                'code' => 'CALC-01',
                'name' => 'Calculation Station',
                'line_id' => Line::query()->create(['code' => 'CALC', 'name' => 'Calculation Line', 'is_active' => true])->id,
                'is_active' => true,
            ])->id,
            'station_name' => 'Calculation Station',
            'line_name' => 'Calculation Line',
            'line_code' => 'CALC',
        ]);
        $fixed = $contract->penaltyClauses()->where('serial_number', 9)->sole();
        $formula = $contract->penaltyClauses()->where('serial_number', 16)->sole();
        $cost = $contract->penaltyClauses()->where('serial_number', 17)->sole();

        $assessment = app(MemoAssessmentService::class)->create($memo, $cmcStaff, WorkflowStepType::CmcAssessment, 1, [
            'items' => [
                ['contract_penalty_clause_id' => $fixed->id, 'proposed_amount' => 500, 'justification' => 'Fixed contractual penalty.'],
                ['contract_penalty_clause_id' => $formula->id, 'proposed_amount' => 6000, 'justification' => 'Two days for three workmen.', 'calculation_inputs' => ['days' => 2, 'workers' => 3]],
                ['contract_penalty_clause_id' => $cost->id, 'proposed_amount' => 51500, 'justification' => 'Fixed penalty plus assessed cost.', 'calculation_inputs' => ['additional_cost' => 1500]],
            ],
            'negative_points' => 0,
            'staff_action' => 'warning',
            'bill_month' => '2026-08',
            'monthly_bill_value' => 100000,
        ]);

        $this->assertSame('58000.00', $assessment->proposed_total);
        $this->assertSame('6000.00', $assessment->items->firstWhere('clause_serial_number', 16)->suggested_amount);
        $this->assertSame('51500.00', $assessment->items->firstWhere('clause_serial_number', 17)->suggested_amount);
        $this->assertSame('Loss of login ID or card', $assessment->items->firstWhere('clause_serial_number', 9)->clause_description);
    }

    public function test_cash_mismatch_penalty_slabs_are_prefilled_editable_and_used_for_assessment(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Cash Mismatch Partner', 'is_active' => true]);
        $line = Line::query()->create(['code' => 'CASH', 'name' => 'Cash Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'CASH-01', 'name' => 'Cash Station', 'line_id' => $line->id, 'is_active' => true]);

        $this->actingAs($administrator)
            ->get(route('contracts.create', ['service_type' => SdmType::Ticketing->value]))
            ->assertOk()
            ->assertSee('Cash mismatch penalty slabs')
            ->assertSee('name="clauses[9][parameters][cash_mismatch_penalty_amounts][1]"', false)
            ->assertSee('value="100"', false);

        $clauses = PenaltyClauseTemplates::for(SdmType::Ticketing);
        $clauses[9]['parameters']['cash_mismatch_penalty_amounts'][1] = 125;

        $this->actingAs($administrator)->post(route('contracts.store'), [
            'contractor_id' => $contractor->id,
            'line_ids' => [$line->id],
            'station_ids' => [$station->id],
            'service_type' => SdmType::Ticketing->value,
            'contract_number' => 'TVS/CASH/01',
            'title' => 'Cash Mismatch Contract',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'clauses' => $clauses,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $contract = ServiceContract::query()->sole();
        $cashMismatchClause = $contract->penaltyClauses()->where('serial_number', 10)->sole();
        $this->assertSame(125, $cashMismatchClause->parameters['cash_mismatch_penalty_amounts'][1]);

        $memo = ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Ticketing->value,
            'serial_number' => 'TVS-CASH-1',
            'contractor_id' => $contractor->id,
            'service_contract_id' => $contract->id,
            'contractor_name' => $contractor->name,
            'station_id' => $station->id,
            'station_name' => $station->name,
            'created_by' => $cmcStaff->id,
        ]);
        $memo->deficiencies()->create(['type' => DeficiencyType::CashCheck->value]);
        $memo->cashCheck()->create(['difference_amount' => '-12.00']);

        $prefill = app(MemoAssessmentService::class)->lineReviewPrefill($memo->fresh());

        $this->assertSame('125.00', $prefill->get($cashMismatchClause->id)['proposed_amount']);
        $this->assertSame(CashMismatchPenalty::defaultAmounts(), PenaltyClauseTemplates::for(SdmType::Ticketing)[9]['parameters']['cash_mismatch_penalty_amounts']);
    }

    public function test_create_form_keeps_active_contract_resolution_in_the_backend(): void
    {
        [$stationUser] = $this->workflowUsers();
        $cmcStaff = $this->user(UserRole::CmcStaff);
        $contractor = Contractor::query()->create(['name' => 'Backend Contract Partner', 'is_active' => true]);
        $contract = $this->contract($contractor, $cmcStaff, SdmType::Cfs, 'CFA/BACKEND');
        $contract->update(['status' => 'published', 'published_at' => now()]);

        $this->actingAs($stationUser)->get(route('sdm-memos.create'))
            ->assertOk()
            ->assertSee('Contractor')
            ->assertDontSee('Active service contract')
            ->assertDontSee('name="service_contract_id"', false);
    }

    public function test_submission_is_blocked_when_backend_cannot_find_an_active_contract(): void
    {
        [$stationUser] = $this->workflowUsers();
        $contractor = Contractor::query()->create(['name' => 'Partner Without Contract', 'is_active' => true]);

        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFA-NO-CONTRACT',
            'contractor_id' => $contractor->id,
            'incident_date' => '2026-08-25',
            'incident_time' => '10:30',
            'staff_name' => 'Outsourced Employee',
            'employee_number' => 'EMP-NO-CONTRACT',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::DressCode->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
        ])->assertSessionHasErrors('contractor_id');

        $this->assertDatabaseCount('service_deficiency_memos', 0);
    }

    private function submittedMemo(User $stationUser, Contractor $contractor, ServiceContract $contract): ServiceDeficiencyMemo
    {
        $this->actingAs($stationUser)->post(route('sdm-memos.store-and-submit'), [
            'service_type' => SdmType::Cfs->value,
            'serial_number' => 'CFA-RETURN-1',
            'contractor_id' => $contractor->id,
            'incident_date' => now()->toDateString(),
            'incident_time' => now('Asia/Kolkata')->format('H:i'),
            'staff_name' => 'Return Employee',
            'employee_number' => 'EMP-RETURN',
            'station_manager_employee_number' => User::query()->role(UserRole::StationManager->value)->value('employee_number'),
            'outsourced_staff_acknowledged' => '1',
            'deficiencies' => [DeficiencyType::DressCode->value],
            'deficiency_remarks' => [DeficiencyType::DressCode->value => 'Uniform was incomplete.'],
        ])->assertRedirect();

        return ServiceDeficiencyMemo::query()->sole();
    }

    private function contract(Contractor $contractor, User $cmcStaff, SdmType $type, string $number): ServiceContract
    {
        $station = Station::query()->with('metroLine')->first();

        if ($station === null) {
            $line = Line::query()->create(['code' => 'CONTRACT-LINE', 'name' => 'Contract Line', 'is_active' => true]);
            $station = Station::query()->create(['code' => 'CONTRACT-STATION', 'name' => 'Contract Station', 'line_id' => $line->id, 'is_active' => true]);
            $station->setRelation('metroLine', $line);
        }

        $contract = ServiceContract::query()->create([
            'contract_group' => str()->ulid()->toString(),
            'version' => 1,
            'contractor_id' => $contractor->id,
            'line_id' => $station->line_id,
            'station_id' => $station->id,
            'service_type' => $type->value,
            'contract_number' => $number,
            'title' => $type->label().' Contract',
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-12-31',
            'cmc_staff_id' => $cmcStaff->id,
            'status' => 'draft',
        ]);
        $contract->seedPenaltyClauses();

        return $contract->refresh();
    }

    private function workflowUsers(): array
    {
        $line = Line::query()->create(['code' => 'RED', 'name' => 'Red Line', 'is_active' => true]);
        $station = Station::query()->create(['code' => 'RED-01', 'name' => 'Red Station', 'line_id' => $line->id, 'is_active' => true]);
        $stationUser = $this->user(UserRole::Station, ['station_id' => $station->id]);
        $stationManager = $this->user(UserRole::StationManager, ['employee_number' => 'SM-CONTRACT']);
        $lineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-CONTRACT']);
        $operationsManager = $this->user(UserRole::ManagerOperations, ['employee_number' => 'MO-CONTRACT']);
        $dgm = $this->user(UserRole::DeputyGeneralManager, ['employee_number' => 'DGM-CONTRACT']);
        $cmcTeamLead = $this->user(UserRole::CmcTeamLead);

        return [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, $station, $cmcTeamLead];
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }
}
