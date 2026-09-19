<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFieldObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'parcel_id' => ['nullable', 'integer', 'exists:parcels,id'],
            'observed_at' => ['required', 'date'],
            'observer' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'data_status' => ['required', Rule::in(['demo', 'measured', 'external', 'unknown'])],
            'burn_severity' => ['nullable', Rule::in(['unburned', 'low', 'moderate', 'high', 'very_high', 'unknown'])],
            'land_cover' => ['nullable', 'string', 'max:100'],
            'slope_deg' => ['nullable', 'numeric', 'between:0,90'],
            'soil_ph' => ['nullable', 'numeric', 'between:0,14'],
            'soil_texture' => ['nullable', 'string', 'max:100'],
            'soil_moisture_pct' => ['nullable', 'numeric', 'between:0,100'],
            'erosion_signs' => ['nullable', 'boolean'],
            'fire_date' => ['nullable', 'date'],
            'values' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
