<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            // Declared location data: what the rule-based recommendation falls
            // back to when no dataset can be sampled for this point.
            'soil_texture' => ['nullable', 'string', 'max:100'],
            'rainfall_mm' => ['nullable', 'integer', 'min:0', 'max:10000'],

            'area_ha' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'fire_event_date' => ['nullable', 'date', 'before_or_equal:today'],
            'objective' => ['nullable', 'string', 'max:255'],
            'crs' => ['nullable', 'string', 'max:50'],
            'geometry' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Beri nama lahan supaya mudah dikenali.',
            'fire_event_date.before_or_equal' => 'Tanggal kebakaran tidak boleh di masa depan.',
            'rainfall_mm.between' => 'Curah hujan tahunan wajar berada di 0-10.000 mm.',
            'latitude.required_with' => 'Latitude dan longitude harus diisi berpasangan.',
            'longitude.required_with' => 'Latitude dan longitude harus diisi berpasangan.',
        ];
    }
}
