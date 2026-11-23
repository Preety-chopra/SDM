<?php

namespace App\Http\Controllers;

use App\Enums\UserPermission;
use App\Http\Requests\ImportOutsourcedStaffRequest;
use App\Services\OutsourcedStaffImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OutsourcedStaffImportController extends Controller
{
    public function template(): BinaryFileResponse
    {
        Gate::authorize(UserPermission::ManageContractors->value);

        return response()->download(resource_path('templates/outsourced-staff-import-sample.xlsx'), headers: [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

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
