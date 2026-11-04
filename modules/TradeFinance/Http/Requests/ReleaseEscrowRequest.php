<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReleaseEscrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'tf_officer'], true);
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
            'document_type' => ['required', 'in:BL,POD'],
            'document_hash' => ['required', 'string', 'regex:/^[0-9a-fA-F]{64}$/'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
