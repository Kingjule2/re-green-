<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLandAnalysisRequest extends FormRequest
{
    /**
     * The maximum accepted upload size, in kilobytes (50 MB).
     */
    private const MAX_KILOBYTES = 50 * 1024;

    /**
     * Authorization is the land policy's job: the controller checks that the
     * account may manage the land before this request is resolved.
     */
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
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.self::MAX_KILOBYTES],

            // When the photo was taken: the monitoring period it belongs to.
            'captured_at' => ['nullable', 'date', 'before_or_equal:now'],
            'capture_source' => ['nullable', Rule::in(['phone', 'camera', 'drone', 'other'])],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Optional per-photo coordinates; the land's own point is used when
            // these are omitted.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],

            // Strict vertical metadata (survey workflow). Missing fields are
            // retained as an explicit incomplete status rather than zeroes.
            'vertical' => ['nullable', 'array'],
            'vertical.h' => ['nullable', 'numeric'],
            'vertical.h_unit' => ['nullable', 'string', 'max:10'],
            'vertical.N' => ['nullable', 'numeric'],
            'vertical.N_unit' => ['nullable', 'string', 'max:10'],
            'vertical.H' => ['nullable', 'numeric'],
            'vertical.H_unit' => ['nullable', 'string', 'max:10'],
            'vertical.delta_h_takeoff' => ['nullable', 'numeric'],
            'vertical.delta_h_takeoff_unit' => ['nullable', 'string', 'max:10'],
            'vertical.d_vertikal_ke_tanah' => ['nullable', 'numeric'],
            'vertical.d_unit' => ['nullable', 'string', 'max:10'],
            'vertical.unit' => ['nullable', 'string', 'max:10'],
            'vertical.vertical_reference' => ['nullable', 'string', 'max:50'],
            'vertical.geoid_model' => ['nullable', 'string', 'max:100'],
            'vertical.epoch' => ['nullable', 'date'],
            'vertical.source' => ['nullable', 'string', 'max:255'],
            'vertical.distance_reference' => ['nullable', 'string', 'max:100'],
            'vertical.surface' => ['nullable', 'string', 'max:50'],
            'vertical.uncertainty_h' => ['nullable', 'numeric', 'min:0'],
            'vertical.uncertainty_N' => ['nullable', 'numeric', 'min:0'],
            'vertical.uncertainty_d' => ['nullable', 'numeric', 'min:0'],
            'vertical.uncertainty_unit' => ['nullable', 'string', 'max:10'],
            'vertical.uncertainty_h_unit' => ['nullable', 'string', 'max:10'],
            'vertical.uncertainty_N_unit' => ['nullable', 'string', 'max:10'],
            'vertical.uncertainty_d_unit' => ['nullable', 'string', 'max:10'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Pilih foto lahan yang ingin dianalisis.',
            'image.mimes' => 'Format foto tidak didukung. Gunakan JPG, PNG, atau WEBP.',
            'image.max' => 'Ukuran foto maksimal 50 MB.',
            'captured_at.before_or_equal' => 'Tanggal foto tidak boleh di masa depan.',
            'latitude.required_with' => 'Latitude dan longitude harus diisi berpasangan.',
            'longitude.required_with' => 'Latitude dan longitude harus diisi berpasangan.',
        ];
    }
}
