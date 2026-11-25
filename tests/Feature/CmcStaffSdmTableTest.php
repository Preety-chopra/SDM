<?php

namespace Tests\Feature;

use App\Enums\DeficiencyType;
use App\Enums\MemoStatus;
use App\Enums\SdmType;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Models\Contractor;
use App\Models\HistoricalSdmImport;
use App\Models\Line;
use App\Models\ServiceDeficiencyMemo;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CmcStaffSdmTableTest extends TestCase
{
    use RefreshDatabase;

    private const COLUMNS = [
        'SDM Serial Number', 'SDM Type', 'SDM deficiency', 'Line', 'Station', 'Incident date',
        'Proposed penalty amount', 'Cumulative Negative Points', 'Proposed Negative Points', 'Proposed Action',
    ];

    private User $viewer;

    private Contractor $contractor;

    private Station $station;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->viewer = User::factory()->create();
        $this->viewer->assignRole(UserRole::CmcStaff->value);
        $this->actingAs($this->viewer);
        $this->contractor = Contractor::factory()->create();
        $line = Line::query()->create(['code' => 'L-7', 'name' => 'Pink Line']);
        $this->station = Station::query()->create(['code' => 'SDM-01', 'name' => 'Central Station', 'line_id' => $line->id]);
    }

    public function test_cmc_staff_list_displays_the_current_proposal_and_prior_awarded_points(): void
    {
        $prior = $this->memo('PRIOR', ['submitted_at' => '2026-09-08 08:00:00']);
        $this->assessment($prior, 1, 3, 'approved');
        $this->assessment($prior, 2, 7, 'approved');
        $this->assessment($prior, 3, 50, 'proposed');

        $legacy = $this->memo('LEGACY', ['submitted_at' => null]);
        $legacy->forceFill(['created_at' => '2026-09-08 09:00:00'])->save();
        $this->assessment($legacy, 1, 4, 'approved');
        $sameTimeEarlier = $this->memo('SAME-TIME-EARLIER');
        $this->assessment($sameTimeEarlier, 1, 1, 'approved');
        $otherStaff = $this->memo('OTHER-STAFF', [
            'employee_number' => '009002', 'submitted_at' => '2026-09-08 08:00:00',
        ]);
        $this->assessment($otherStaff, 1, 50, 'approved');
        $otherContractor = $this->memo('OTHER-CONTRACTOR', [
            'contractor_id' => Contractor::factory()->create()->id, 'submitted_at' => '2026-09-08 08:00:00',
        ]);
        $this->assessment($otherContractor, 1, 50, 'approved');
        $cancelled = $this->memo('CANCELLED', [
            'status' => MemoStatus::Cancelled, 'submitted_at' => '2026-09-08 08:00:00',
        ]);
        $this->assessment($cancelled, 1, 50, 'approved');
        $unapproved = $this->memo('UNAPPROVED', ['submitted_at' => '2026-09-08 08:00:00']);
        $this->assessment($unapproved, 1, 50, 'proposed');
        $later = $this->memo('LATER', ['submitted_at' => '2026-09-11 08:00:00']);
        $this->assessment($later, 1, 50, 'approved');

        $current = $this->memo('CURRENT-SDM');
        $current->deficiencies()->create(['type' => DeficiencyType::LateReporting]);
        $this->assessment($current, 1, 2, 'approved');
        $this->assessment($current, 2, 11, 'proposed', ['staff_action' => 'final_warning', 'proposed_total' => 4321]);
        $sameTimeLater = $this->memo('SAME-TIME-LATER');
        $this->assessment($sameTimeLater, 1, 50, 'approved');
        $this->importedRecord('IMPORT-SDM');

        $response = $this->get(route('sdm-memos.index', ['deficiency_type' => DeficiencyType::LateReporting->value]))->assertOk();

        $xpath = $this->xpath($response);
        $table = '(//table)[1]';
        $this->assertColumns($xpath, $table);
        $this->assertSame(
            ['CURRENT-SDM', 'CFA', 'Late reporting', 'L-7', 'Central Station', '09-09-2026', '₹4,321.00', '14.5', '11', 'Final Warning'],
            $this->requiredCells($xpath, $table, 'CURRENT-SDM'),
        );
    }

    public function test_cmc_staff_dashboard_shows_sdm_columns_in_recent_and_chart_tables(): void
    {
        $current = $this->memo('DASHBOARD-SDM');
        $current->deficiencies()->create(['type' => DeficiencyType::LateReporting]);
        $this->assessment($current, 1, 8, 'proposed', ['staff_action' => 'recall', 'proposed_total' => 2500]);

        $response = $this->get(route('dashboard', $this->dashboardDates()))->assertOk();

        $xpath = $this->xpath($response);
        $recentTable = '(//table[not(ancestor::*[@role="dialog"])])[1]';
        $this->assertColumns($xpath, $recentTable);
        $this->assertColumns($xpath, '(//table[ancestor::*[@role="dialog"]])[1]');
        $cells = $this->requiredCells($xpath, $recentTable, 'DASHBOARD-SDM');
        $this->assertSame('DASHBOARD-SDM', $cells[0]);
        $this->assertSame(['₹2,500.00', '0', '8', 'Recall'], array_slice($cells, 6));
    }

    public function test_cmc_staff_chart_data_contains_the_line_proposal_and_prior_points_before_staff_assessment(): void
    {
        $current = $this->memo('CHART-SDM');
        $current->deficiencies()->create(['type' => DeficiencyType::LateReporting]);
        $this->assessment($current, 1, null, 'proposed', [
            'source_stage' => WorkflowStepType::LineReview->value, 'staff_action' => null, 'proposed_total' => 1750,
        ]);
        $this->importedRecord('IMPORT-SDM');
        $this->memo('UNASSIGNED-CHART-SDM', ['cmc_staff_id' => null]);

        $this->getJson(route('dashboard.chart-sdms', $this->dashboardDates()))
            ->assertOk()
            ->assertJsonCount(1, 'memos')
            ->assertJsonStructure(['memos' => ['*' => ['proposed_negative_points', 'proposed_action']]])
            ->assertJsonPath('memos.0.serial_number', 'CHART-SDM')
            ->assertJsonPath('memos.0.service', 'CFA')
            ->assertJsonPath('memos.0.deficiency', 'Late reporting')
            ->assertJsonPath('memos.0.line', 'L-7')
            ->assertJsonPath('memos.0.station', 'Central Station')
            ->assertJsonPath('memos.0.incident_date', '09 Sep 2026')
            ->assertJsonPath('memos.0.proposed_penalty_amount', '₹1,750.00')
            ->assertJsonPath('memos.0.cumulative_negative_points', '2.5')
            ->assertJsonPath('memos.0.proposed_negative_points', null)
            ->assertJsonPath('memos.0.proposed_action', null);
    }

    public function test_cmc_staff_employee_history_shows_the_required_fields_for_live_and_imported_sdms(): void
    {
        $current = $this->memo('HISTORY-LIVE');
        $current->deficiencies()->create(['type' => DeficiencyType::LateReporting]);
        $this->assessment($current, 1, 0, 'proposed', ['proposed_total' => 0]);
        $this->importedRecord('HISTORY-IMPORTED');
        $this->importedRecord('HISTORY-EARLIER', ['incident_date' => '2024-12-01', 'negative_points' => '1.25']);

        $response = $this->get(route('employee-history.index', [
            'contractor_id' => $this->contractor->id, 'employee_number' => '009001',
        ]))->assertOk();

        $xpath = $this->xpath($response);
        $liveTable = '//table[caption="SDM records, most recent incident first"]';
        $importedTable = '//table[caption="Imported employee records, most recent incident first"]';
        $this->assertColumns($xpath, $liveTable);
        $this->assertColumns($xpath, $importedTable);
        $this->assertSame(['₹0.00', '3.75', '0', 'Warning'], array_slice($this->requiredCells($xpath, $liveTable, 'HISTORY-LIVE'), 6));
        $importedCells = $this->requiredCells($xpath, $importedTable, 'HISTORY-IMPORTED');
        $this->assertSame(['HISTORY-IMPORTED', '—', 'Left post early', 'L-1', 'Imported Station'], array_slice($importedCells, 0, 5));
        $this->assertSame(['₹999.50', '1.25', '2.5', 'Written warning'], array_slice($importedCells, 6));
    }

    #[TestWith([UserRole::CmcTeamLead])]
    #[TestWith([UserRole::ManagerOperations])]
    #[TestWith([UserRole::DeputyGeneralManager])]
    public function test_cmc_reviewers_see_row_highlights_for_current_staff_proposals(UserRole $role): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole($role->value);
        $finalWarning = $this->memo('FINAL-WARNING');
        $this->assessment($finalWarning, 1, 3, 'proposed', ['staff_action' => 'final_warning']);
        $finalWarning->workflowSteps()->create([
            'round' => 1, 'sequence' => 5, 'stage' => WorkflowStepType::CmcAssessment,
            'role' => UserRole::CmcStaff->value, 'assigned_to' => $this->viewer->id, 'assigned_name' => $this->viewer->name,
            'decision' => 'forwarded', 'acted_by' => $this->viewer->id,
        ]);
        $nextStaff = User::factory()->create();
        $nextStaff->assignRole(UserRole::CmcStaff->value);
        $finalWarning->update(['cmc_staff_id' => $nextStaff->id]);
        $recall = $this->memo('RECALL');
        $this->assessment($recall, 1, 5, 'approved', ['staff_action' => 'recall']);
        $revised = $this->memo('REVISED');
        $this->assessment($revised, 1, 5, 'proposed', ['staff_action' => 'recall']);
        $this->assessment($revised, 2, 1, 'proposed');
        $line = $this->memo('LINE-ONLY');
        $this->assessment($line, 1, 3, 'proposed', ['source_stage' => WorkflowStepType::LineReview->value, 'staff_action' => 'final_warning']);
        $lead = $this->memo('DIRECT-VERIFICATION');
        $teamLead = User::factory()->create();
        $teamLead->assignRole(UserRole::CmcTeamLead->value);
        $this->assessment($lead, 1, 3, 'proposed', ['staff_action' => 'final_warning']);
        $this->assessment($lead, 2, 5, 'proposed', ['staff_action' => 'recall', 'proposed_by' => $teamLead->id]);

        $xpath = $this->xpath($this->actingAs($reviewer)->get(route('sdm-memos.index'))->assertOk());

        $this->assertColumns($xpath, '(//table)[1]');
        $this->assertSame(['₹1,000.00', '0', '3', 'Final Warning'], array_slice($this->requiredCells($xpath, '(//table)[1]', 'FINAL-WARNING'), 6));
        $this->assertHighlightedRow($xpath, $finalWarning, 'Final Warning');
        $this->assertHighlightedRow($xpath, $recall, 'Recall');
        $this->assertHighlightedRow($xpath, $revised, null);
        $this->assertHighlightedRow($xpath, $line, null);
        $this->assertHighlightedRow($xpath, $lead, null);
    }

    #[TestWith([UserRole::CmcTeamLead, WorkflowStepType::CmcAssignment])]
    #[TestWith([UserRole::ManagerOperations, WorkflowStepType::AmCmcToDgm])]
    #[TestWith([UserRole::DeputyGeneralManager, WorkflowStepType::DgmApproval])]
    public function test_reviewer_dashboard_chart_and_history_show_cmc_columns_and_staff_proposal(UserRole $role, WorkflowStepType $stage): void
    {
        $reviewer = User::factory()->create();
        $reviewer->assignRole($role->value);
        $memo = $this->memo('REVIEW-RECALL');
        $this->importedRecord('IMPORTED-SDM');
        $this->assessment($memo, 1, 5, 'proposed', ['staff_action' => 'recall']);
        $memo->workflowSteps()->create([
            'round' => 1, 'sequence' => 7, 'stage' => $stage,
            'role' => $role->value, 'assigned_to' => $reviewer->id,
            'assigned_name' => $reviewer->name,
        ]);

        $this->actingAs($reviewer);
        $dashboard = $this->xpath($this->get(route('dashboard', $this->dashboardDates()))->assertOk());
        $recent = '(//table[not(ancestor::*[@role="dialog"])])[1]';
        $this->assertColumns($dashboard, $recent);
        $this->assertColumns($dashboard, '(//table[ancestor::*[@role="dialog"]])[1]');
        $expectedProposal = ['₹1,000.00', '2.5', '5', 'Recall'];
        $this->assertSame($expectedProposal, array_slice($this->requiredCells($dashboard, $recent, 'REVIEW-RECALL'), 6));
        $this->assertHighlightedRow($dashboard, $memo, 'Recall');
        $this->getJson(route('dashboard.chart-sdms', $this->dashboardDates()))->assertOk()
            ->assertJsonPath('memos.0.serial_number', 'REVIEW-RECALL')
            ->assertJsonPath('memos.0.proposed_penalty_amount', '₹1,000.00')
            ->assertJsonPath('memos.0.cumulative_negative_points', '2.5')
            ->assertJsonPath('memos.0.proposed_negative_points', 5)
            ->assertJsonPath('memos.0.proposed_action', 'Recall')
            ->assertJsonPath('memos.0.highlighted_proposal', 'Recall');
        $history = $this->xpath($this->get(route('employee-history.index', ['contractor_id' => $this->contractor->id, 'employee_number' => '009001']))->assertOk());
        $live = '//table[caption="SDM records, most recent incident first"]';
        $imported = '//table[caption="Imported employee records, most recent incident first"]';
        $this->assertColumns($history, $live);
        $this->assertColumns($history, $imported);
        $this->assertSame($expectedProposal, array_slice($this->requiredCells($history, $live, 'REVIEW-RECALL'), 6));
        $this->assertSame(['₹999.50', '0', '2.5', 'Written warning'], array_slice($this->requiredCells($history, $imported, 'IMPORTED-SDM'), 6));
        $this->assertHighlightedRow($history, $memo, 'Recall');
    }

    public function test_cmc_staff_see_the_action_without_a_reviewer_row_highlight(): void
    {
        $memo = $this->memo('STAFF-RECALL');
        $this->assessment($memo, 1, 5, 'proposed', ['staff_action' => 'recall']);

        $response = $this->get(route('sdm-memos.index'))->assertOk()->assertSee('Recall');
        $this->assertHighlightedRow($this->xpath($response), $memo, null);
    }

    private function assertHighlightedRow(DOMXPath $xpath, ServiceDeficiencyMemo $memo, ?string $action): void
    {
        $url = route('sdm-memos.show', $memo);
        $row = $xpath->query("//tbody/tr[.//a[@href='{$url}']]")->item(0);
        $this->assertNotNull($row, "Missing SDM row {$memo->serial_number}");
        $this->assertSame($action ?? '', $row->getAttribute('data-cmc-proposal'), $memo->serial_number);
        if ($action !== null) {
            $this->assertStringContainsString($action, $row->textContent);
        }
    }

    private function memo(string $serialNumber, array $attributes = []): ServiceDeficiencyMemo
    {
        return ServiceDeficiencyMemo::query()->create([
            'service_type' => SdmType::Cfs,
            'serial_number' => $serialNumber,
            'station_id' => $this->station->id,
            'station_name' => $this->station->name,
            'line_code' => 'L-7',
            'contractor_id' => $this->contractor->id,
            'staff_name' => 'SDM Table Employee',
            'employee_number' => '009001',
            'incident_date' => '2026-09-09',
            'status' => MemoStatus::CmcScrutiny,
            'workflow_version' => 2,
            'cmc_staff_id' => $this->viewer->id,
            'submitted_at' => '2026-09-10 08:00:00',
            ...$attributes,
        ]);
    }

    private function assessment(ServiceDeficiencyMemo $memo, int $version, ?int $points, string $status, array $attributes = []): void
    {
        $memo->assessments()->create([
            'round' => 1,
            'version' => $version,
            'source_stage' => WorkflowStepType::CmcAssessment->value,
            'proposed_by' => $this->viewer->id,
            'negative_points' => $points,
            'staff_action' => 'warning',
            'proposed_total' => 1000,
            'status' => $status,
            ...$attributes,
        ]);
    }

    private function importedRecord(string $serialNumber, array $attributes = []): void
    {
        $import = HistoricalSdmImport::query()->create([
            'contractor_id' => $this->contractor->id,
            'source_file' => 'history.xlsx',
            'source_sha256' => hash('sha256', $serialNumber),
            'source_sheet' => 'SDMs',
            'row_count' => 1,
            'summary' => [],
        ]);

        $import->records()->create([
            'contractor_id' => $this->contractor->id,
            'source_row' => 1,
            'memo_number' => $serialNumber,
            'employee_number' => '009001',
            'incident_date' => '2025-01-01',
            'source_line' => 'L-1',
            'source_station' => 'Imported Station',
            'discrepancies' => 'Left post early',
            'negative_points' => '2.50',
            'contractor_penalty' => '999.50',
            'recommended_action' => 'Written warning',
            'import_issues' => [],
            ...$attributes,
        ]);
    }

    private function dashboardDates(): array
    {
        return ['date_from' => '2026-09-01', 'date_to' => '2026-09-30'];
    }

    private function xpath(TestResponse $response): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);

        return new DOMXPath($document);
    }

    private function assertColumns(DOMXPath $xpath, string $table): void
    {
        $required = array_map(strtolower(...), self::COLUMNS);
        $actual = array_map(strtolower(...), $this->columnLabels($xpath, $table));

        $this->assertSame($required, array_values(array_intersect($actual, $required)));
    }

    private function columnLabels(DOMXPath $xpath, string $table): array
    {
        return array_map(
            fn (DOMNode $cell): string => $this->cellText($xpath->query('.//summary/span[1]', $cell)->item(0) ?? $cell),
            iterator_to_array($xpath->query($table.'/thead/tr/th')),
        );
    }

    private function requiredCells(DOMXPath $xpath, string $table, string $serialNumber): array
    {
        $labels = array_map(strtolower(...), $this->columnLabels($xpath, $table));
        $required = array_map(strtolower(...), self::COLUMNS);
        $serialColumn = array_search($required[0], $labels, true) + 1;
        $row = $xpath->query($table."/tbody/tr[td[{$serialColumn}][contains(., '{$serialNumber}')]]")->item(0);
        $this->assertNotNull($row, "Missing SDM row {$serialNumber}");
        $cells = iterator_to_array($xpath->query('td', $row));

        return array_map(
            fn (string $heading): string => $this->cellText($cells[array_search($heading, $labels, true)]),
            $required,
        );
    }

    private function cellText(DOMNode $cell): string
    {
        return trim(preg_replace('/\s+/u', ' ', $cell->textContent));
    }
}
