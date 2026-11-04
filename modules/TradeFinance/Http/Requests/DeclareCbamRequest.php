<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeclareCbamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'compliance_officer'], true);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->headers->has('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->headers->get('Idempotency-Key')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'container_number' => ['required', 'string', 'max:32'],
            'product_code' => ['required', 'string', 'max:32'],
            'origin_country' => ['required', 'string', 'size:2'],
            'net_mass_kg' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
