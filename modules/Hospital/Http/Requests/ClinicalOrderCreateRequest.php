<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ClinicalOrderCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'admission_id' => ['required', 'integer', 'exists:hsp_admissions,id'],
            'order_type' => ['required', 'string', 'in:LAB,PHARMACY,RADIOLOGY,PROCEDURE,DIET'],
            'item_code' => ['required', 'string', 'max:50'],
            'item_name' => ['required', 'string', 'max:255'],
            'urgency' => ['required', 'string', 'in:ROUTINE,URGENT,STAT'],
            'parameters' => ['nullable', 'array'],
        ];
    }
}
