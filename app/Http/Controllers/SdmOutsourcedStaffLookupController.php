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

        return response()->json($staff->lookupForMemo($data['staff_identifier'], (int) $data['contractor_id']))
            ->header('Cache-Control', 'no-store, private');
    }
}
