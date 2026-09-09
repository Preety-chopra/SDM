<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['memo_deficiency_id', 'booked_shift_from', 'booked_shift_to', 'actual_reporting_time'])]
class MemoLateReportingDetail extends Model
{
    public function delayInMinutes(): int
    {
        $bookedMinutes = $this->minutesSinceMidnight($this->booked_shift_from);
        $actualMinutes = $this->minutesSinceMidnight($this->actual_reporting_time);

        return max(0, $actualMinutes - $bookedMinutes);
    }

    public function delayLabel(): string
    {
        $delay = $this->delayInMinutes();
        if ($delay < 60) {
            return $delay.' '.($delay === 1 ? 'minute' : 'minutes');
        }

        $hours = intdiv($delay, 60);
        $minutes = $delay % 60;
        $label = $hours.' '.($hours === 1 ? 'hour' : 'hours');

        return $minutes > 0 ? $label.' '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes') : $label;
    }

    public function deficiency(): BelongsTo
    {
        return $this->belongsTo(MemoDeficiency::class, 'memo_deficiency_id');
    }

    private function minutesSinceMidnight(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }
}
