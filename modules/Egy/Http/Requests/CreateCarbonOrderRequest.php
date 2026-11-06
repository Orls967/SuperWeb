<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CreateCarbonOrderRequest extends FormRequest
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
            'side' => ['required', 'string', 'in:BUY,SELL'],
            'batch_id' => ['required', 'integer', 'exists:egy_carbon_credit_batches,id'],
            'quantity_kg_co2e' => ['required', 'integer', 'min:1000'],
            'limit_price_minor_per_ton' => ['required', 'integer', 'min:10000'],
        ];
    }
}
