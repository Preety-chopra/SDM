<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\StoreMemoDeductionRequest;
use App\Http\Requests\StoreMemoObjectionRequest;
use App\Http\Requests\StoreMemoPenaltyLetterRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\MemoPenaltyLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MemoPenaltyLifecycleController extends Controller
{
    public function __construct(private MemoPenaltyLifecycleService $lifecycle) {}

    public function letter(StoreMemoPenaltyLetterRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->lifecycle->issueLetter($sdmMemo, $request->user(), $request->validated());

        return back()->with('status', 'Signed penalty letter issued and objection window started.');
    }

    public function objection(StoreMemoObjectionRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->lifecycle->recordObjection($sdmMemo, $request->user(), $request->validated());

        return back()->with('status', 'Objection recorded and routed for reassessment.');
    }

    public function noObjection(Request $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        abort_unless($request->user()->can(UserPermission::ManageObjections->value), 403);
        abort_unless($request->user()->can('manageCmcLifecycle', $sdmMemo), 403);
        $this->lifecycle->confirmNoObjection($sdmMemo, $request->user());

        return back()->with('status', 'Objection window closed without objection.');
    }

    public function deduction(StoreMemoDeductionRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->lifecycle->recordDeduction($sdmMemo, $request->user(), $request->validated());

        return back()->with('status', 'Deduction recorded and SDM closed.');
    }
}
