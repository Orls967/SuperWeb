<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class QuotePromiseRequest extends FormRequest
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
            'item_code' => ['required', 'string', 'max:60'],
            'quantity' => ['required', 'integer', 'min:1'],
            'requested_period' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'order_reference' => ['nullable', 'string', 'max:32'],
            'as_of_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
