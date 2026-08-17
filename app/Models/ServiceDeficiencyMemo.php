<?php

namespace App\Models;

use App\Enums\MemoStatus;
use App\Enums\SdmType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['service_type', 'serial_number', 'station_id', 'station_name', 'line_name', 'contractor_id', 'contractor_name', 'staff_name', 'employee_number', 'incident_date', 'status', 'created_by', 'updated_by', 'submitted_at'])]
class ServiceDeficiencyMemo extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = ['status' => MemoStatus::Draft->value];

    public function station(): BelongsTo { return $this->belongsTo(Station::class); }
    public function contractor(): BelongsTo { return $this->belongsTo(Contractor::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function deficiencies(): HasMany { return $this->hasMany(MemoDeficiency::class, 'memo_id'); }
    public function publicComplaint(): HasOne { return $this->hasOne(MemoPublicComplaint::class, 'memo_id'); }
    public function cashCheck(): HasOne { return $this->hasOne(MemoCashCheck::class, 'memo_id'); }
    public function attachments(): HasMany { return $this->hasMany(MemoAttachment::class, 'memo_id'); }

    protected function casts(): array
    {
        return [
            'service_type' => SdmType::class,
            'status' => MemoStatus::class,
            'incident_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }
}
