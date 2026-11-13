<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SafetyStockProposalRequest extends FormRequest
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
            'item_code' => ['required', 'string', 'max:32'],
            'supplier_ref' => ['nullable', 'string', 'max:64'],
            'abc_class' => ['nullable', 'string', 'in:A,B,C'],
            'annual_usage_value_idr' => ['nullable', 'integer', 'min:0'],
            'total_catalog_value_idr' => ['nullable', 'integer', 'min:1'],
            'demand_history' => ['nullable', 'array', 'min:1'],
            'demand_history.*' => ['integer', 'min:0'],
            'fallback_lead_time_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'echelon_weights' => ['nullable', 'array'],
            'echelon_weights.*' => ['integer', 'min:0'],
        ];
    }
}
