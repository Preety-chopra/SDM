<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\UpdateLineSectionsRequest;
use App\Models\Line;
use App\Services\LineTopologyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LineSectionsController extends Controller
{
    public function __construct(private LineTopologyService $topology) {}

    public function edit(Line $line): View
    {
        Gate::authorize(UserPermission::ManageLines->value);

        return view('master-data.line-sections', $this->topology->editor($line));
    }

    public function update(UpdateLineSectionsRequest $request, Line $line): RedirectResponse
    {
        $this->topology->replaceSections($line, $request->validated('sections'), $request->user());

        return redirect()->route('lines.sections.edit', $line)->with('status', 'Sections updated. Existing records retain their original line details.');
    }
}
