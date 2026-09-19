<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalysisReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approved', 'needs_review', 'rejected', 'corrected'])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'corrections' => ['nullable', 'array'],
        ];
    }
}
