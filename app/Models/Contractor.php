<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'contact_person', 'phone', 'address', 'is_active'])]
class Contractor extends Model
{
    use HasFactory;

    public function memos(): HasMany
    {
        return $this->hasMany(ServiceDeficiencyMemo::class);
    }

    public function serviceContracts(): HasMany
    {
        return $this->hasMany(ServiceContract::class);
    }

    public function outsourcedStaff(): HasMany
    {
        return $this->hasMany(OutsourcedStaff::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
