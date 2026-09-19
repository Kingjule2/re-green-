<?php

namespace App\Http\Requests;

use App\Models\ReportSnapshot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'scope' => ['required', Rule::in([ReportSnapshot::SCOPE_ALL, ReportSnapshot::SCOPE_LAND])],
            // Required when the report covers a single land; the controller
            // checks the account may actually read that land.
            'land_id' => ['nullable', 'integer', 'exists:lands,id', 'required_if:scope,land'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'scope.required' => 'Tentukan cakupan laporan: seluruh lahan atau satu lahan.',
            'land_id.required_if' => 'Pilih lahan untuk laporan dengan cakupan satu lahan.',
        ];
    }
}
