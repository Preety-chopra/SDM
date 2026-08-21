<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subordinate_id', 'supervisor_id'])]
class ReportingRelationship extends Model
{
    public function subordinate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subordinate_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
