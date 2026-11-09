<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TelemetryRecordRequest extends FormRequest
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
            'heart_rate' => ['required', 'integer', 'min:20', 'max:250'],
            'respiratory_rate' => ['required', 'integer', 'min:4', 'max:60'],
            'spo2' => ['required', 'numeric', 'min:50', 'max:100'],
            'systolic_bp' => ['required', 'integer', 'min:40', 'max:300'],
            'diastolic_bp' => ['nullable', 'integer', 'min:20', 'max:200'],
            'temperature' => ['required', 'numeric', 'min:30.0', 'max:45.0'],
            'consciousness' => ['required', 'string', 'in:ALERT,VOICE,PAIN,UNRESPONSIVE'],
            'on_oxygen' => ['nullable', 'boolean'],
        ];
    }
}
