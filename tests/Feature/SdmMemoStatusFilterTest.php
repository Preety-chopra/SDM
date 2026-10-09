<?php

namespace Tests\Feature;

use App\Enums\MemoStatus;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SdmMemoStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_am_memo_page_uses_the_same_current_status_options_as_the_dashboard(): void
    {
        $response = $this->actingAs($this->user(UserRole::AssistantManager))
            ->get(route('sdm-memos.index'))
            ->assertOk()
            ->assertSee('Current status')
            ->assertDontSee('value="draft"', false)
            ->assertDontSee('value="pending_deduction"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $options = (new \DOMXPath($document))->query('//select[@id="status"]/option');

        $actual = [];

        foreach ($options as $option) {
            $actual[$option->getAttribute('value')] = trim($option->textContent);
        }

        $this->assertSame([
            '' => 'All statuses',
            MemoStatus::LineReview->value => 'Pending (at current user level)',
            MemoStatus::AmCmcReview->value => 'AM CMC Review',
            MemoStatus::CmcAssignment->value => 'CMC Assignment',
            MemoStatus::CmcScrutiny->value => 'CMC Scrutiny',
            MemoStatus::DgmApproval->value => 'DGM Approval',
            MemoStatus::PendingPenaltyLetter->value => 'Pending Penalty Letter',
            MemoStatus::ObjectionWindow->value => 'Objection Window',
            MemoStatus::ObjectionReview->value => 'Objection Review',
            MemoStatus::Cancelled->value => 'Cancelled',
            MemoStatus::Closed->value => 'Closed',
        ], $actual);
    }

    public function test_cmc_and_dgm_memo_pages_do_not_display_draft_status(): void
    {
        foreach ([
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('sdm-memos.index'))
                ->assertOk()
                ->assertSee('Current status')
                ->assertDontSee('value="draft"', false);
        }
    }

    public function test_transferred_memos_tab_is_hidden_for_downstream_cmc_roles_only(): void
    {
        foreach ([
            UserRole::ManagerOperations,
            UserRole::CmcTeamLead,
            UserRole::CmcStaff,
            UserRole::DeputyGeneralManager,
        ] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('sdm-memos.index'))
                ->assertOk()
                ->assertSee('All Memos')
                ->assertSee('Pending Memos')
                ->assertDontSee('Transferred Memos');
        }

        foreach ([UserRole::StationManager, UserRole::AssistantManager] as $role) {
            $this->actingAs($this->user($role))
                ->get(route('sdm-memos.index'))
                ->assertOk()
                ->assertSee('Transferred Memos');
        }
    }

    private function user(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
