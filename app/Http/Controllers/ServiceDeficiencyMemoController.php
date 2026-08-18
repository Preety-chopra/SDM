<?php

namespace App\Http\Controllers;

use App\Enums\DeficiencyType;
use App\Enums\SdmType;
use App\Http\Requests\StoreAndSubmitMemoRequest;
use App\Http\Requests\StoreMemoRequest;
use App\Http\Requests\SubmitMemoRequest;
use App\Http\Requests\UpdateMemoRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\MasterDataService;
use App\Services\ServiceDeficiencyMemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ServiceDeficiencyMemoController extends Controller
{
    public function __construct(
        private ServiceDeficiencyMemoService $memos,
        private MasterDataService $masterData,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ServiceDeficiencyMemo::class);

        return view('sdm-memos.index', ['memos' => $this->memos->paginateFor($request->user())]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', ServiceDeficiencyMemo::class);

        return $this->formView($request);
    }

    public function store(StoreMemoRequest $request): RedirectResponse
    {
        $memo = $this->memos->createDraft($request->user(), $request->validated());

        return redirect()->route('sdm-memos.edit', $memo)->with('status', 'Draft saved.');
    }

    public function storeAndSubmit(StoreAndSubmitMemoRequest $request): RedirectResponse
    {
        $memo = $this->memos->createSubmitted($request->user(), $request->validated());

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    public function show(ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('view', $sdmMemo);
        $sdmMemo->load(['creator:id,name', 'deficiencies.lateReporting', 'publicComplaint.station', 'cashCheck', 'attachments']);

        return view('sdm-memos.show', ['memo' => $sdmMemo]);
    }

    public function edit(Request $request, ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('update', $sdmMemo);
        $sdmMemo->load(['deficiencies.lateReporting', 'publicComplaint', 'cashCheck', 'attachments']);

        return $this->formView($request, $sdmMemo);
    }

    public function update(UpdateMemoRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $this->memos->updateDraft($sdmMemo, $request->user(), $request->validated());

        return back()->with('status', 'Draft saved.');
    }

    public function submit(SubmitMemoRequest $request, ServiceDeficiencyMemo $sdmMemo): RedirectResponse
    {
        $memo = $this->memos->submit($sdmMemo, $request->user(), $request->validated());

        return redirect()->route('sdm-memos.show', $memo)->with('status', 'SDM memo submitted successfully.');
    }

    private function formView(Request $request, ?ServiceDeficiencyMemo $memo = null): View
    {
        $station = $request->user()->station()->with('metroLine')->firstOrFail();

        return view('sdm-memos.form', [
            'memo' => $memo,
            'station' => $station,
            'contractors' => $this->masterData->contractors(true),
            'stations' => $this->masterData->stations(true),
            'sdmTypes' => SdmType::cases(),
            'deficiencyTypes' => DeficiencyType::cases(),
        ]);
    }
}
