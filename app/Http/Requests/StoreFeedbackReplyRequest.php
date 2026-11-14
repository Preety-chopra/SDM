<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreFeedbackReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->routeIs('feedback.follow-up')) {
            Gate::authorize('viewOwn', $this->route('feedback'));

            return true;
        }

        return $this->user()->can('reply', $this->route('feedback'));
    }

    public function rules(): array
    {
        return ['reply' => ['bail', 'required', 'string', 'max:5000']];
    }
}
