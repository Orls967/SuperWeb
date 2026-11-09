<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DischargePatientRequest extends FormRequest
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
            'discharge_disposition' => ['required', 'string', 'in:HOME,REFERRED,AGAINST_MEDICAL_ADVICE,DECEASED'],
            'discharge_summary' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
