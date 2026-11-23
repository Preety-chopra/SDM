<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['line_id', 'code', 'name', 'station_ids', 'effective_from', 'retired_at'])]
class LineSection extends Model
{
    public function line(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    protected function casts(): array
    {
        return ['station_ids' => 'array', 'effective_from' => 'datetime', 'retired_at' => 'datetime'];
    }
}
