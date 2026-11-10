<?php

namespace App\Http\Requests;

class SubmitAftCashCheckReportRequest extends SaveAftCashCheckReportRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['action' => 'submit']);
    }
}
