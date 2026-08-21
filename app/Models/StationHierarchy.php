<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['station_id', 'station_manager_id', 'line_manager_id', 'operations_manager_id', 'dgm_id'])]
class StationHierarchy extends Model
{
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function stationManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'station_manager_id');
    }

    public function lineManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'line_manager_id');
    }

    public function operationsManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operations_manager_id');
    }

    public function dgm(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dgm_id');
    }
}
