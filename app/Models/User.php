<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'employee_number', 'phone', 'designation', 'station_id', 'password', 'is_enabled', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = [
        'is_enabled' => true,
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function createdMemos(): HasMany
    {
        return $this->hasMany(ServiceDeficiencyMemo::class, 'created_by');
    }

    public function assignedMemoApprovals(): HasMany
    {
        return $this->hasMany(MemoApproval::class, 'assigned_to');
    }

    public function managedStationHierarchies(): HasMany
    {
        return $this->hasMany(StationHierarchy::class, 'station_manager_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
