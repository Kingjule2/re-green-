<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDroneAnalysisRequest extends FormRequest
{
    /**
     * The maximum accepted upload size, in kilobytes (50 MB).
     */
    private const MAX_KILOBYTES = 50 * 1024;

    /**
     * Determine if the user is authorized to make this request.
     *
     * NOTE: The MVP upload endpoint is intentionally open (no authentication
     * layer is installed yet). Add a policy / auth middleware before exposing
     * this publicly in production.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,tif,tiff', 'max:'.self::MAX_KILOBYTES],

            // Optional geospatial metadata.
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_name' => ['nullable', 'string', 'max:255'],

            // Optional survey metadata.
            'survey_date' => ['nullable', 'date'],
            'drone_model' => ['nullable', 'string', 'max:255'],
            'flight_altitude' => ['nullable', 'string', 'max:50'],
            'image_type' => ['nullable', 'string', 'max:50'],

            'project_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * Custom validation messages surfaced to the UI.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'image.required' => 'Please select a drone image to analyze.',
            'image.mimes' => 'This image format is not supported. Use JPG, PNG, or TIFF.',
            'image.max' => 'Maximum file size is 50 MB.',
        ];
    }
}
