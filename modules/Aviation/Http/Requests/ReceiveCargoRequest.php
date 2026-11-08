<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveCargoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'integer', 'exists:avi_flights,id'],
            'weight_g' => ['required', 'integer', 'min:1'],
            'nature_of_goods' => ['required', 'string', 'max:128'],
            'un_number' => ['nullable', 'string', 'max:16'],
            'transport_mode' => ['nullable', 'string', 'in:PAX,CAO'],
            'shipper_party_id' => ['nullable', 'integer'],
            'consignee_party_id' => ['nullable', 'integer'],
            'volume_mm3' => ['nullable', 'integer'],
            'uld_holdover_minutes' => ['nullable', 'integer'],
            'required_temperature_min_c10' => ['nullable', 'integer'],
            'required_temperature_max_c10' => ['nullable', 'integer'],
            'uld_container_code' => ['nullable', 'string', 'max:16'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->header('Idempotency-Key')) {
            abort(422, 'Header Idempotency-Key wajib disertakan.');
        }
    }
}
