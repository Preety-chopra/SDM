<?php

namespace App\Models;

use App\Support\OutsourcedStaffIdentifier;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['import_id', 'contractor_id', 'outsourced_staff_id', 'station_id', 'source_row', 'source_usn', 'source_date', 'incident_date', 'memo_number', 'source_line', 'source_station', 'employee_name', 'employee_number', 'aadhaar_number', 'discrepancies', 'recommended_action', 'contractor_penalty', 'cp_number', 'negative_points', 'import_issues'])]
class HistoricalSdm extends Model
{
    protected $hidden = ['aadhaar_number'];

    public function import(): BelongsTo
    {
        return $this->belongsTo(HistoricalSdmImport::class, 'import_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function outsourcedStaff(): BelongsTo
    {
        return $this->belongsTo(OutsourcedStaff::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    protected function maskedAadhaar(): Attribute
    {
        return Attribute::get(function (): ?string {
            $number = OutsourcedStaffIdentifier::aadhaar($this->aadhaar_number ?? '');

            return $number === null ? null : OutsourcedStaffIdentifier::mask($number);
        });
    }

    protected function casts(): array
    {
        return ['incident_date' => 'date', 'aadhaar_number' => 'encrypted', 'contractor_penalty' => 'decimal:2', 'negative_points' => 'decimal:2', 'import_issues' => 'array'];
    }
}
