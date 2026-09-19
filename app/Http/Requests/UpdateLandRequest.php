<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Every field is optional on a PATCH: the land page edits one thing at a
     * time, and a missing key must never clear a value the farmer recorded.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'location_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'soil_texture' => ['sometimes', 'nullable', 'string', 'max:100'],
            'rainfall_mm' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000'],
            'area_ha' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000'],
            'fire_event_date' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
            'objective' => ['sometimes', 'nullable', 'string', 'max:255'],
            'crs' => ['sometimes', 'nullable', 'string', 'max:50'],
            'geometry' => ['sometimes', 'nullable', 'array'],
            'status' => ['prohibited'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ];
    }
}
