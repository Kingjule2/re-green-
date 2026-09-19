<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    private const MAX_KILOBYTES = 100 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'asset' => ['required', 'file', 'mimes:jpg,jpeg,png,tif,tiff,geotiff,csv,json', 'max:'.self::MAX_KILOBYTES],
            'parcel_id' => ['nullable', 'integer', 'exists:parcels,id'],
            'type' => ['required', Rule::in(['satellite_before', 'satellite_after', 'drone_rgb', 'drone_multispectral', 'field_photo', 'boundary', 'other'])],
            'captured_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'data_status' => ['required', Rule::in(['demo', 'measured', 'external', 'unknown'])],
            'source' => ['nullable', 'string', 'max:255'],
            'dataset' => ['nullable', 'string', 'max:255'],
            'provider' => ['nullable', 'string', 'max:255'],
            'service_url' => ['nullable', 'url', 'max:1000'],
            'vertical' => ['nullable', 'array'],
        ];
    }
}
