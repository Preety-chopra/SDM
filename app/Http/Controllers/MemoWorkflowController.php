<?php

namespace App\Http\Controllers;

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

    public function reassign(UpdateMemoCmcAssignmentRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->workflow->reassignCmcStaff($sdmMemo, $request->user(), $request->integer('cmc_staff_id'));

        return redirect()->route('sdm-memos.show', $sdmMemo)->with('status', 'CMC Staff reassigned.');
    }
}
