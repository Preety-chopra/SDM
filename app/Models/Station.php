<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'line_id', 'is_active'])]
class Station extends Model
{
    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function metroLine(): BelongsTo
    {
        return $this->belongsTo(Line::class, 'line_id');
    }

    public function memos(): HasMany
    {
        return $this->hasMany(ServiceDeficiencyMemo::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
