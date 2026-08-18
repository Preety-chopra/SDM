<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\SaveLineRequest;
use App\Models\Line;
use App\Services\MasterDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LineController extends Controller
{
    public function __construct(private MasterDataService $masterData) {}

    public function index(): View
    {
        Gate::authorize(UserPermission::ManageLines->value);

        return view('master-data.lines', ['lines' => $this->masterData->lines()]);
    }

    public function store(SaveLineRequest $request): RedirectResponse
    {
        $this->masterData->saveLine(null, $request->validated());

        return back()->with('status', 'Line created.');
    }

    public function update(SaveLineRequest $request, Line $line): RedirectResponse
    {
        $this->masterData->saveLine($line, $request->validated());

        return back()->with('status', 'Line updated.');
    }

    public function destroy(Line $line): RedirectResponse
    {
        Gate::authorize(UserPermission::ManageLines->value);
        $this->masterData->deleteLine($line);

        return back()->with('status', 'Line deleted.');
    }
}
