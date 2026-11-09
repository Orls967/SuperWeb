<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogMedicalWasteRequest extends FormRequest
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
            'waste_category' => ['required', 'string', 'in:SHARPS,INFECTIOUS,PATHOLOGICAL,PHARMACEUTICAL,CYTOTOXIC,CHEMICAL'],
            'weight_kg' => ['required', 'numeric', 'min:0.01'],
            'source_ward_code' => ['required', 'string', 'max:32'],
            'handler_staff_name' => ['required', 'string', 'max:128'],
        ];
    }
}
