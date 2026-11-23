<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeHistoryRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\EmployeeHistoryService;
use App\Services\MasterDataService;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class EmployeeHistoryController extends Controller
{
    public function __construct(private EmployeeHistoryService $history, private MasterDataService $masterData) {}

    public function index(EmployeeHistoryRequest $request): View
    {
        return view('employee-history.index', [
            ...$this->history->search($request->safe()->only(['contractor_id', 'employee_number'])),
            'contractors' => $this->masterData->contractors(),
            'memo' => null,
        ]);
    }

    public function show(ServiceDeficiencyMemo $sdmMemo): View
    {
        Gate::authorize('viewEmployeeHistory', ServiceDeficiencyMemo::class);
        Gate::authorize('view', $sdmMemo);

        return view('employee-history.index', [
            ...$this->history->forMemo($sdmMemo),
            'memo' => $sdmMemo,
        ]);
    }
}
