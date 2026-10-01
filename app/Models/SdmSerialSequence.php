<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['period', 'last_number'])]
final class SdmSerialSequence extends Model
{
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
