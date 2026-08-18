<?php

namespace App\Http\Requests;

use App\Models\ServiceDeficiencyMemo;

class StoreMemoRequest extends MemoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceDeficiencyMemo::class);
    }

    protected function isSubmission(): bool
    {
        return false;
    }
}
