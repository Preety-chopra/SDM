<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['contractor_id', 'imported_by', 'source_file', 'source_sha256', 'source_sheet', 'row_count', 'summary'])]
class HistoricalSdmImport extends Model
{
    public function records(): HasMany
    {
        return $this->hasMany(HistoricalSdm::class, 'import_id');
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    protected function casts(): array
    {
        return ['summary' => 'array', 'row_count' => 'integer'];
    }
}
