<?php

namespace App\Http\Requests;

use App\Services\Agriculture\CropCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SelectCropRequest extends FormRequest
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
            'crop_id' => ['required', 'string', Rule::in(array_column(CropCatalog::all(), 'id'))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'crop_id.in' => 'Tanaman itu tidak ada di katalog rekomendasi.',
        ];
    }
}
