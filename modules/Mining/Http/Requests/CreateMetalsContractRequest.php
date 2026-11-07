<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateMetalsContractRequest extends FormRequest
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
            'counterparty_name' => ['required', 'string', 'max:128'],
            'metal_symbol' => ['required', 'string', 'in:LME-NI,LME-CU,LME-CO'],
            'contract_type' => ['required', 'string', 'in:PHYSICAL_OFFTAKE,HEDGE_FORWARD,HEDGE_SWAP'],
            'contract_quantity_metric_tons' => ['required', 'numeric', 'gt:0'],
            'fixed_price_usd_cents_per_ton' => ['required', 'integer', 'gt:0'],
            'pricing_basis' => ['nullable', 'string', 'max:32'],
            'delivery_period_start' => ['nullable', 'date'],
            'delivery_period_end' => ['nullable', 'date', 'after_or_equal:delivery_period_start'],
        ];
    }
}
