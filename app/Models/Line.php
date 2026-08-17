<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Line extends Model
{
    use HasFactory;

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
