<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdmitPatientRequest extends FormRequest
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
            'patient_id' => ['required', 'integer', 'exists:hsp_patients,id'],
            'bed_id' => ['required', 'integer', 'exists:hsp_beds,id'],
            'required_class' => ['required', 'string', 'in:CLASS_3,CLASS_2,CLASS_1,VIP,VVIP,ICU,NICU,PICU,HCU,ISOLATION'],
            'deposit_idr' => ['required', 'integer', 'min:0'],
            'diagnosis_icd10' => ['nullable', 'string', 'max:10'],
            'admission_type' => ['nullable', 'string', 'in:ELECTIVE,EMERGENCY,TRANSFER'],
        ];
    }
}
