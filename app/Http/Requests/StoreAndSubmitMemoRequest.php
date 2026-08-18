<?php

namespace App\Http\Requests;

use App\Enums\UserPermission;
use App\Models\ServiceDeficiencyMemo;

class StoreAndSubmitMemoRequest extends MemoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', ServiceDeficiencyMemo::class)
            && $this->user()->can(UserPermission::SubmitOwnDraftMemos->value);
    }

    protected function isSubmission(): bool
    {
        return true;
    }
}
