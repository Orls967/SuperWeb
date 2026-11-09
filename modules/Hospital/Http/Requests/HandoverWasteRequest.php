<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HandoverWasteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'manifest_id' => ['required', 'integer', 'exists:hsp_medical_waste_manifests,id'],
            'transporter_company_name' => ['required', 'string', 'max:128'],
            'vehicle_plate_no' => ['required', 'string', 'max:32'],
            'driver_name' => ['required', 'string', 'max:128'],
            'handover_weight_kg' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
