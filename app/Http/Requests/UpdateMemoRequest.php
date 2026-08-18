<?php

namespace App\Http\Requests;

class UpdateMemoRequest extends MemoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('sdm_memo'));
    }

    protected function isSubmission(): bool
    {
        return false;
    }
}
