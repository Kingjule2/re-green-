<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDnbrAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'before' => ['required', 'array'],
            'before.nir' => ['required', 'array'],
            'before.swir' => ['required', 'array'],
            'before.reflectance_scale' => ['required', 'numeric', 'gt:0'],
            'before.acquired_at' => ['nullable', 'date'],
            'after' => ['required', 'array'],
            'after.nir' => ['required', 'array'],
            'after.swir' => ['required', 'array'],
            'after.reflectance_scale' => ['required', 'numeric', 'gt:0'],
            'after.acquired_at' => ['nullable', 'date'],
            'clear_mask' => ['nullable', 'array'],
            'crs' => ['nullable', 'string', 'max:100'],
            'pixel_size_m' => ['nullable', 'numeric', 'gt:0'],
            'data_status' => ['required', Rule::in(['demo', 'measured', 'external', 'unknown'])],
            'source' => ['nullable', 'string', 'max:255'],
        ];
    }
}
