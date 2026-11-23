<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergeLinesRequest;
use App\Services\LineTopologyService;
use Illuminate\Http\RedirectResponse;

class MergeLinesController extends Controller
{
    public function __invoke(MergeLinesRequest $request, LineTopologyService $topology): RedirectResponse
    {
        $topology->merge($request->validated('line_ids'), $request->integer('target_line_id'), $request->user());

        return redirect()->route('lines.index')->with('status', 'Lines merged. Existing records and contract coverage have been preserved.');
    }
}
