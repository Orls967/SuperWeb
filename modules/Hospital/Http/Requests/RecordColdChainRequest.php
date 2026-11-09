<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordColdChainRequest extends FormRequest
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
            'sensor_device_code' => ['required', 'string', 'max:32'],
            'fridge_unit_code' => ['required', 'string', 'max:32'],
            'storage_zone' => ['required', 'string', 'in:CHILLER_2_TO_8,FREEZER_MINUS_20,ULTRA_LOW_MINUS_80,ROOM_TEMP_15_TO_25'],
            'temperature_c' => ['required', 'numeric'],
            'humidity_pct' => ['nullable', 'numeric', 'between:0,100'],
            'recorded_at' => ['nullable', 'date'],
        ];
    }
}
