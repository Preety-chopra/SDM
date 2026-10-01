<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\Line;
use App\Models\ReportingRelationship;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use App\Services\MemoAssessmentService;
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
            'line_id' => $line->id,
            'station_id' => $station->id,
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
        $this->assertSame('draft', $revision->status);
        $this->assertCount(12, $revision->penaltyClauses);
    }

    public function test_administrator_maps_a_contract_to_a_line_and_station_when_creating_and_updating_it(): void
    {
        $administrator = $this->user(UserRole::Administrator);
        $contractor = Contractor::query()->create(['name' => 'Mapped Contract Partner', 'is_active' => true]);
        $redLine = Line::query()->create(['code' => 'RED-MAP', 'name' => 'Red Mapping Line', 'is_active' => true]);
        $blueLine = Line::query()->create(['code' => 'BLUE-MAP', 'name' => 'Blue Mapping Line', 'is_active' => true]);
        $redStation = Station::query()->create(['code' => 'RED-MAP-01', 'name' => 'Red Mapping Station', 'line_id' => $redLine->id, 'is_active' => true]);
        $blueStation = Station::query()->create(['code' => 'BLUE-MAP-01', 'name' => 'Blue Mapping Station', 'line_id' => $blueLine->id, 'is_active' => true]);

        $this->actingAs($administrator)
            ->get(route('contracts.create'))
            ->assertOk()
            ->assertSee('name="line_id"', false)
            ->assertSee('name="station_id"', false)
            ->assertSee('x-data="contractForm"', false)
            ->assertSee('Red Mapping Station');

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
            'line_id' => $redLine->id,
            'station_id' => $blueStation->id,
        ])->assertSessionHasErrors('station_id');

        $this->actingAs($administrator)->post(route('contracts.store'), [
            ...$contractData,
            'line_id' => $redLine->id,
            'station_id' => $redStation->id,
        ])->assertRedirect();

        $contract = ServiceContract::query()->sole();
        $this->assertSame($redLine->id, $contract->line_id);
        $this->assertSame($redStation->id, $contract->station_id);

        $this->actingAs($administrator)
            ->get(route('contracts.edit', $contract))
            ->assertOk()
            ->assertSee('Red Mapping Line')
            ->assertSee('Red Mapping Station');

        $this->actingAs($administrator)->put(route('contracts.update', $contract), [
            ...$contractData,
            'line_id' => $blueLine->id,
            'station_id' => $blueStation->id,
        ])->assertRedirect(route('contracts.show', $contract));

        $contract->refresh();
        $this->assertSame($blueLine->id, $contract->line_id);
        $this->assertSame($blueStation->id, $contract->station_id);
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
        $this->assertSame([
            WorkflowStepType::StationManager,
            WorkflowStepType::LineReview,
            WorkflowStepType::AmCmcToScrutiny,
            WorkflowStepType::CmcAssignment,
            WorkflowStepType::CmcAssessment,
            WorkflowStepType::AmCmcToDgm,
            WorkflowStepType::DgmApproval,
        ], $memo->workflowSteps()->orderBy('sequence')->get()->pluck('stage')->all());

        $alternativeLineManager = $this->user(UserRole::AssistantManager, ['employee_number' => 'AM-ALTERNATIVE']);
        $inactiveLineManager = $this->user(UserRole::AssistantManager, [
            'employee_number' => 'AM-INACTIVE',
            'is_enabled' => false,
        ]);

        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
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
            ->assertSee('Serial number')
            ->assertSee('SDM serial number')
            ->assertSee('SDM type')
            ->assertSee('SDM deficiency')
            ->assertSee('Line / station')
            ->assertSee('Incident date')
            ->assertSee('Submitted on')
            ->assertSee('Current status')
            ->assertSee('Last updated on')
            ->assertSee('Action')
            ->assertSee('Pending (at SM level)')
            ->assertSee('Poor Dress Code / Failure to Follow Instructions')
            ->assertSee('01-08-2026 10:00');
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
            ->assertDontSee('AM/CMC forwarding for scrutiny')
            ->assertDontSee('CMC Team Lead staff assignment')
            ->assertDontSee('CMC Staff scrutiny and assessment')
            ->assertDontSee('AM/CMC forwarding to DGM/CMC')
            ->assertDontSee('DGM/CMC approval');
        $this->actingAs($stationManager)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertDontSee('Contract assessment')
            ->assertDontSee('Memo CMC Staff')
            ->assertSee('Station Manager review')
            ->assertSee('Assistant Manager/Manager(Line) review')
            ->assertDontSee('AM/CMC forwarding for scrutiny')
            ->assertDontSee('CMC Team Lead staff assignment')
            ->assertDontSee('CMC Staff scrutiny and assessment')
            ->assertDontSee('AM/CMC forwarding to DGM/CMC')
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
            ->assertSee('Serial number')
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
            ->assertSee('Memo CMC Staff')
            ->assertSee("x-data='{ selectedClauses: [\"{$clause->id}\"] }'", false)
            ->assertSee('Penalties matching the recorded deficiencies are pre-selected. You may add or remove clauses before forwarding.')
            ->assertSee('name="items[0][proposed_amount]"', false)
            ->assertSee('value="200.00"', false)
            ->assertSee('data-timeline-state="forwarded"', false)
            ->assertSee('data-timeline-state="current"', false)
            ->assertSee('DGM/CMC approval')
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
            ->assertDontSee('With AM/CMC forwarding for scrutiny');
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'processed_by_am']))
            ->assertOk()
            ->assertSee($memo->serial_number);
        $this->actingAs($lineManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('With AM/CMC forwarding for scrutiny')
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
            ->assertSee("x-data='{ selectedClauses: [\"{$addedClause->id}\"] }'", false)
            ->assertSee('name="items[1][proposed_amount]"', false)
            ->assertSee('value="150.00"', false)
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
            'negative_points' => 2,
            'staff_action' => 'warning',
            'bill_month' => '2026-08',
            'monthly_bill_value' => '10000.00',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($memo->assessments()->latest('version')->firstOrFail()->items()->sole()->justification);
        $this->actingAs($cmcStaff)->get(route('sdm-memos.show', $memo))
            ->assertOk()
            ->assertSee('AM/Manager (Line) Review 1')
            ->assertSee('CMC Review 1')
            ->assertDontSee('Proposal v1')
            ->assertDontSee('round 1');
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();

        $memo->refresh();
        $this->assertSame(MemoStatus::PendingPenaltyLetter, $memo->status);
        $this->assertSame('200.00', $memo->assessments()->where('status', 'approved')->sole()->proposed_total);
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
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward'])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'approve'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(MemoStatus::PendingPenaltyLetter, $memo->fresh()->status);
        $this->assertSame(['proposed', 'superseded', 'approved'], $memo->assessments()->orderBy('version')->pluck('status')->all());
        $this->assertSame('180.00', $memo->assessments()->where('status', 'approved')->sole()->proposed_total);
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
            'amount' => '180.00',
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(MemoStatus::Closed, $memo->status);
        $this->assertSame('180.00', $memo->deduction->amount);
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
            'negative_points' => 1,
            'staff_action' => 'warning',
            'bill_month' => '2026-08',
            'monthly_bill_value' => 10000,
        ]);
        $this->actingAs($operationsManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'forward']);

        $this->actingAs($dgm)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'return',
            'remarks' => 'Reassess the proposed amount.',
        ])->assertRedirect();

        $memo->refresh();
        $this->assertSame(MemoStatus::CmcScrutiny, $memo->status);
        $this->assertSame(2, $memo->currentWorkflowStep->round);
        $this->assertSame(WorkflowStepType::CmcAssessment, $memo->currentWorkflowStep->stage);
        $this->assertSame(10, $memo->workflowSteps()->count());
        $this->assertSame('returned', $memo->workflowSteps()->where('stage', WorkflowStepType::DgmApproval->value)->where('round', 1)->value('decision'));
        $this->assertSame($cmcStaff->id, $memo->cmc_staff_id);
        $this->assertSame($cmcStaff->id, $memo->currentWorkflowStep->assigned_to);
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

    public function test_contract_workflow_am_line_can_cancel_with_mandatory_remarks(): void
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
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), ['action' => 'cancel'])
            ->assertSessionHasErrors('remarks');
        $this->actingAs($lineManager)->post(route('sdm-memos.workflow.store', $memo), [
            'action' => 'cancel',
            'remarks' => 'Cancelled by AM/Line after review.',
        ])->assertRedirect();

        $this->assertSame(MemoStatus::Cancelled, $memo->fresh()->status);
        $this->actingAs($stationUser)->get(route('sdm-memos.show', $memo))->assertOk()->assertSee('Cancelled by AM/Line');
        $this->actingAs($stationManager)->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('>Cancelled by AM</span>', false)
            ->assertDontSee('Cancelled by AM/Line');
        $this->actingAs($stationManager)->get(route('sdm-memos.index', ['status' => 'cancelled_by_am']))
            ->assertOk()
            ->assertSee($memo->serial_number);
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
            'staff_action' => 'none',
            'bill_month' => '2026-08',
            'monthly_bill_value' => 100000,
        ]);

        $this->assertSame('58000.00', $assessment->proposed_total);
        $this->assertSame('6000.00', $assessment->items->firstWhere('clause_serial_number', 16)->suggested_amount);
        $this->assertSame('51500.00', $assessment->items->firstWhere('clause_serial_number', 17)->suggested_amount);
        $this->assertSame('Loss of login ID or card', $assessment->items->firstWhere('clause_serial_number', 9)->clause_description);
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

        ReportingRelationship::query()->create(['subordinate_id' => $stationManager->id, 'supervisor_id' => $lineManager->id]);
        ReportingRelationship::query()->create(['subordinate_id' => $lineManager->id, 'supervisor_id' => $operationsManager->id]);
        ReportingRelationship::query()->create(['subordinate_id' => $operationsManager->id, 'supervisor_id' => $dgm->id]);

        return [$stationUser, $stationManager, $lineManager, $operationsManager, $dgm, $station, $cmcTeamLead];
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role->value);

        return $user;
    }
}
