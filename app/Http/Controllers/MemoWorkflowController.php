<?php

namespace App\Http\Controllers;

use App\Enums\WorkflowStepType;
use App\Http\Requests\BulkForwardMemosRequest;
use App\Http\Requests\StoreMemoWorkflowActionRequest;
use App\Http\Requests\UpdateMemoCmcAssignmentRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\MemoWorkflowService;
use Illuminate\Http\RedirectResponse;

class MemoWorkflowController extends Controller
{
    public function __construct(private MemoWorkflowService $workflow) {}

    public function store(StoreMemoWorkflowActionRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->workflow->act($sdmMemo, $request->user(), $request->validated());

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

    public function reassign(UpdateMemoCmcAssignmentRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->workflow->reassignCmcStaff($sdmMemo, $request->user(), $request->integer('cmc_staff_id'));

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'CMC Staff reassigned.');
    }
}
