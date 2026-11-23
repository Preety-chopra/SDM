<?php

namespace App\Http\Controllers;

use App\Http\Requests\LookupOutsourcedStaffRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\OutsourcedStaffService;
use Illuminate\Http\JsonResponse;

class SdmOutsourcedStaffLookupController extends Controller
{
    public function __invoke(LookupOutsourcedStaffRequest $request, OutsourcedStaffService $staff, ?ServiceDeficiencyMemo $sdmMemo = null): JsonResponse
    {
        $data = $request->validated();
        $result = $staff->lookupForMemo($data['staff_identifier'], (int) $data['contractor_id']);
        if ($request->boolean('suggest')) {
            $result['suggestions'] = $staff->suggestionsForMemo($data['staff_identifier'], (int) $data['contractor_id']);
        }

        return response()->json($result)
            ->header('Cache-Control', 'no-store, private');
    }
}
