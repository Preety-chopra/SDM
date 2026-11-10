<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportOutsourcedStaffRequest;
use App\Services\OutsourcedStaffImportService;
use Illuminate\Http\RedirectResponse;

class OutsourcedStaffImportController extends Controller
{
    public function __invoke(ImportOutsourcedStaffRequest $request, OutsourcedStaffImportService $importer): RedirectResponse
    {
        $data = $request->validated();
        $result = $importer->import(
            $request->file('file')->getRealPath(),
            array_filter($data['agency_map'] ?? []),
            $data['invalid_aadhaar'],
            $request->user(),
        );

        return to_route('outsourced-staff.index')->with('import_result', $result)
            ->with('status', "Import complete: {$result['created']} staff added; {$result['skipped']} existing employee IDs skipped.");
    }
}
