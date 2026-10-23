<?php

namespace App\Http\Controllers;

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

        if ($request->user()->hasRole(UserRole::AssistantManager->value)
            && in_array($data['action'], ['cancel', 'cancel_after_counselling'], true)) {
            $message = $data['action'] === 'cancel_after_counselling'
                ? 'SDM cancelled after counselling.'
                : 'SDM cancelled.';

            return redirect()->route('sdm-memos.index')->with('status', $message);
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
        );
        $label = $destination === WorkflowStepType::AmCmcToScrutiny
            ? 'CMC Team SM for scrutiny'
            : 'DGM/CMC';

        $memoLabel = $count === 1 ? 'SDM' : 'SDMs';

        return redirect()->route('sdm-memos.index', $request->filters())
            ->with('status', "{$count} {$memoLabel} forwarded to {$label}.");
    }

    public function bulkCmcAction(BulkCmcMemoActionRequest $request): RedirectResponse
    {
        $action = $request->string('action')->toString();
        $count = $this->workflow->bulkCmcAction(
            $request->user(),
            $action,
            $request->memoIds(),
            $request->filled('cmc_staff_id') ? $request->integer('cmc_staff_id') : null,
            $request->string('remarks')->toString() ?: null,
        );
        $label = match ($action) {
            'assign' => 'assigned to CMC Staff',
            'forward' => 'forwarded to Manager CMC',
            'return' => 'reverted for rechecking',
        };

        return redirect()->route('sdm-memos.index')
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
