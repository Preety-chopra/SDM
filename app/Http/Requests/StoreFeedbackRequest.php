<?php

namespace App\Http\Requests;

use App\Models\Feedback;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Feedback::class);
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:5000'],
            'page_url' => [
                'bail', 'required', 'string', 'max:2048', 'url:http,https',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $origin = $this->getSchemeAndHttpHost();

                    if ($value !== $origin && ! str_starts_with($value, $origin.'/')) {
                        $fail('The page URL must belong to this application.');
                    }
                },
            ],
        ];
    }
}
