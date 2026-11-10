<?php

namespace App\Models;

use App\Support\OutsourcedStaffIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['contractor_id', 'employee_id', 'name', 'father_name', 'date_of_birth', 'aadhaar_number', 'category', 'gender', 'designation', 'entry_permit_number', 'verification_status', 'is_active'])]
class OutsourcedStaff extends Model
{
    use HasFactory;

    protected $hidden = ['aadhaar_number', 'aadhaar_lookup_hash'];

    protected static function booted(): void
    {
        static::saving(function (OutsourcedStaff $staff): void {
            if ($staff->isDirty('aadhaar_number')) {
                $staff->aadhaar_lookup_hash = OutsourcedStaffIdentifier::hash($staff->aadhaar_number);
            }
        });
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    protected function maskedAadhaar(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->aadhaar_number === null ? null : 'XXXX XXXX '.substr($this->aadhaar_number, -4));
    }

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'aadhaar_number' => 'encrypted', 'is_active' => 'boolean'];
    }
}
