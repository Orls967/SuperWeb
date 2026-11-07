<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchByproductSlagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'run_id' => ['required', 'uuid'],
            'dispatch_tons' => ['required', 'numeric', 'gt:0'],
            'buyer_party_code' => ['required', 'string', 'max:64'],
            'unit_price_idr_per_ton' => ['nullable', 'integer', 'gt:0'],
            'utilization_application' => ['nullable', 'string', 'max:128'],
            'carrier_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
