<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProposeMurabahahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'syariah_officer'], true);
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
            'customer_user_id' => ['required', 'integer', 'exists:users,id'],
            'supplier_party_ref' => ['required', 'integer', 'min:1'],
            'asset_description' => ['required', 'string', 'max:191'],
            'cost_price_idr' => ['required', 'integer', 'min:1000'],
            'margin_amount_idr' => ['required', 'integer', 'min:0'],
            'tenor_months' => ['required', 'integer', 'min:1', 'max:360'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
