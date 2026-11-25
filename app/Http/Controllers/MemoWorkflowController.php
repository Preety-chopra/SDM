<?php

namespace App\Http\Controllers;

use App\Enums\AmCmcMemoCategory;
use App\Enums\UserRole;
use App\Enums\WorkflowStepType;
use App\Http\Requests\BulkCmcMemoActionRequest;
use App\Http\Requests\BulkForwardMemosRequest;
use App\Http\Requests\StoreMemoWorkflowActionRequest;
use App\Http\Requests\UpdateMemoCmcAssignmentRequest;
use App\Http\Requests\UpdateMemoCmcTeamLeadRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\MemoWorkflowService;
use Illuminate\Http\RedirectResponse;

class MemoWorkflowController extends Controller
{
    public function __construct(private MemoWorkflowService $workflow) {}

    public function store(StoreMemoWorkflowActionRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $data = $request->validated();
        $this->workflow->act($sdmMemo, $request->user(), $data);

        if ($data['action'] === 'return_to_line') {
            return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'SDM reverted to AM/Manager (Line).');
        }

        if ($request->user()->hasRole(UserRole::AssistantManager->value)
            || ($data['action'] === 'forward' && $request->user()->hasRole(UserRole::CmcStaff->value))) {
            $message = match ($data['action']) {
                'cancel_after_counselling' => 'SDM cancelled after counselling.',
                'cancel' => 'SDM cancelled.',
                default => 'SDM workflow updated.',
            };

            return redirect()->route('sdm-memos.index', ['category' => 'pending'])->with('status', $message);
        }

        if ($request->user()->hasRole(UserRole::StationManager->value)) {
            return redirect()->route('sdm-memos.index')->with('status', 'SDM workflow updated.');
        }

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'SDM workflow updated.');
    }

    public function bulkForward(BulkForwardMemosRequest $request): RedirectResponse
    {
        $destination = $request->destination();
        $count = $this->workflow->bulkForward(
            $request->user(),
            $destination,
            $request->filters(),
            $request->memoIds(),
            $request->selectsAll(),
            $request->isReceivedFromLines(),
        );
        $label = $destination === WorkflowStepType::AmCmcToScrutiny
            ? 'CMC Team SM for scrutiny'
            : 'DGM/CMC';

        $memoLabel = $count === 1 ? 'SDM' : 'SDMs';

        $parameters = $request->filters();
        if ($request->isReceivedFromLines()) {
            $parameters['category'] = AmCmcMemoCategory::ReceivedFromLines->value;
            $label = 'SM/CMC for verification';
        }

        return redirect()->route('sdm-memos.index', $parameters)
            ->with('status', "{$count} {$memoLabel} forwarded to {$label}.");
    }

    public function bulkCmcAction(BulkCmcMemoActionRequest $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $amCmcReview = $request->isAmCmcReview();
        $count = $this->workflow->bulkCmcAction(
            $request->user(),
            $action,
            $request->memoIds(),
            $request->filled('cmc_staff_id') ? $request->integer('cmc_staff_id') : null,
            $request->validated('remarks'),
            amCmcReview: $amCmcReview,
        );
        $label = match ($action) {
            'assign' => 'assigned to CMC Staff',
            'forward' => $amCmcReview ? 'sent to DGM/CMC for approval' : 'forwarded to Manager CMC',
            'return' => $amCmcReview ? 'reverted to SM/CMC for rechecking' : 'reverted for rechecking',
        };

        return redirect()->route('sdm-memos.index', $request->safe()->only(['category', 'service_type']))
            ->with('status', "{$count} ".($count === 1 ? 'SDM' : 'SDMs')." {$label}.");
    }

    public function reassign(UpdateMemoCmcAssignmentRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->workflow->reassignCmcStaff($sdmMemo, $request->user(), $request->integer('cmc_staff_id'));

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'CMC Staff reassigned.');
    }

    public function reassignCmcTeamLead(UpdateMemoCmcTeamLeadRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->workflow->reassignCmcTeamLead(
            $sdmMemo,
            $request->user(),
            $request->integer('cmc_team_lead_id'),
        );

        return redirect()->route('sdm-memos.show', $sdmMemo)
            ->with('status', 'SDM re-sent to another SM/CMC.');
    }
}
