<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitCbamDeclarationRequest extends FormRequest
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
            'export_contract_number' => ['required', 'string', 'max:60'],
            'container_number' => ['required', 'string', 'max:50'],
            'hs_code' => ['required', 'string', 'max:20'],
            'product_tonnage_kg' => ['required', 'integer', 'min:1000'],
            'specific_emission_factor_bps' => ['required', 'integer', 'min:100'],
            'eu_benchmark_factor_bps' => ['required', 'integer', 'min:100'],
            'carbon_price_eur_minor_per_ton' => ['required', 'integer', 'min:1000'],
            'eur_to_idr_exchange_rate_minor' => ['required', 'integer', 'min:1000000'],
        ];
    }
}
