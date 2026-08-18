<?php

namespace App\Http\Requests;

class SubmitMemoRequest extends MemoRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('submit', $this->route('sdm_memo'));
    }

    protected function isSubmission(): bool
    {
        return true;
    }
}
