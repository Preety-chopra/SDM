<?php

namespace App\Http\Controllers;

use App\Http\Requests\LineManagerDashboardMemosRequest;
use App\Models\ServiceDeficiencyMemo;
use App\Services\LineManagerDashboardService;
use Illuminate\Http\JsonResponse;

class LineManagerDashboardMemosController extends Controller
{
    public function __construct(private LineManagerDashboardService $dashboard) {}

    public function __invoke(LineManagerDashboardMemosRequest $request): JsonResponse
    {
        $memos = $this->dashboard->memosForLine(
            $request->user(),
            $request->lineCode(),
            $request->dateFrom(),
            $request->dateTo(),
            $request->serviceType(),
            $request->status(),
            101,
        );

        return response()->json([
            'line_code' => $request->lineCode(),
            'memos' => $memos->take(100)->map(fn (ServiceDeficiencyMemo $memo): array => [
                'id' => $memo->id,
                'service' => $memo->service_type->code(),
                'station' => $memo->station_name ?: 'Not recorded',
                'status' => $memo->status->label(),
                'submitted_at' => $memo->submitted_at?->format('d M Y'),
                'url' => route('sdm-memos.show', $memo),
            ])->values(),
            'has_more' => $memos->count() > 100,
        ]);
    }
}
