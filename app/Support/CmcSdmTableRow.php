<?php

namespace App\Support;

use App\Models\HistoricalSdm;
use App\Models\ServiceDeficiencyMemo;
use Illuminate\Support\Str;

final class CmcSdmTableRow
{
    public const ASSESSMENT_COLUMNS = [
        'proposed_penalty_amount' => 'Proposed Penalty Amount',
        'cumulative_negative_points' => 'Cumulative Negative Points',
        'proposed_negative_points' => 'Proposed Negative Points',
        'proposed_action' => 'Proposed Action',
    ];

    public static function highlightedProposal(ServiceDeficiencyMemo $memo): ?string
    {
        return match ($memo->cmc_staff_proposal ?? null) {
            'final_warning' => 'Final Warning',
            'recall' => 'Recall',
            default => null,
        };
    }

    /** @return array<string, int|string|null> */
    public static function fromMemo(ServiceDeficiencyMemo $memo): array
    {
        $assessment = $memo->latestAssessment;

        return [
            'serial_number' => $memo->serial_number,
            'service' => $memo->service_type->code(),
            'deficiency' => $memo->deficiencyLabels()->join(', ') ?: null,
            'line' => $memo->line_code ?: $memo->line_name,
            'station' => $memo->station_name,
            'incident_date' => $memo->incident_date?->format('d M Y'),
            'proposed_penalty_amount' => self::formatPenalty($assessment?->proposed_total),
            'cumulative_negative_points' => $memo->contractor_id && filled($memo->employee_number)
                ? self::formatPoints($memo->cumulative_negative_points)
                : null,
            'proposed_negative_points' => $assessment?->negative_points === null ? null : (int) $assessment->negative_points,
            'proposed_action' => filled($assessment?->staff_action) ? Str::headline($assessment->staff_action) : null,
        ];
    }

    /** @return array<string, string|null> */
    public static function fromHistorical(HistoricalSdm $memo): array
    {
        return [
            'serial_number' => $memo->memo_number,
            'service' => null,
            'deficiency' => $memo->discrepancies,
            'line' => $memo->source_line,
            'station' => $memo->source_station,
            'incident_date' => $memo->incident_date?->format('d M Y') ?? $memo->source_date,
            'proposed_penalty_amount' => self::formatPenalty($memo->contractor_penalty),
            'cumulative_negative_points' => $memo->incident_date && filled($memo->employee_number)
                ? self::formatPoints($memo->cumulative_negative_points)
                : null,
            'proposed_negative_points' => self::formatPoints($memo->negative_points),
            'proposed_action' => $memo->recommended_action,
        ];
    }

    private static function formatPenalty(?string $amount): ?string
    {
        return $amount === null ? null : '₹'.number_format((float) $amount, 2);
    }

    private static function formatPoints(int|float|string|null $points): ?string
    {
        return $points === null ? null : rtrim(rtrim(number_format((float) $points, 2, '.', ''), '0'), '.');
    }
}
