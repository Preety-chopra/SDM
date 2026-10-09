<?php

namespace App\Models;

use App\Support\LineColorPalette;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_active'])]
class Line extends Model
{
    use HasFactory;

    protected $attributes = ['color' => LineColorPalette::DEFAULT];

    protected static function booted(): void
    {
        static::saving(function (Line $line): void {
            $line->color = LineColorPalette::for($line->code, $line->name);
        });
    }

    public function stations(): HasMany
    {
        return $this->hasMany(Station::class);
    }

    public function serviceContracts(): BelongsToMany
    {
        return $this->belongsToMany(ServiceContract::class, 'service_contract_line');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
