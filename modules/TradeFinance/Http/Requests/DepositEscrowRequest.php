<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DepositEscrowRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Kepemilikan importir diperiksa di controller setelah validasi (importir hanya mendanai atas namanya).
        return in_array($this->user()?->role, ['admin', 'tf_officer', 'importer'], true);
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
            'letter_of_credit_id' => ['required', 'uuid', 'exists:tf_letters_of_credit,id'],
            'importer_user_id' => ['required', 'integer', 'exists:users,id'],
            'exporter_user_id' => ['required', 'integer', 'different:importer_user_id', 'exists:users,id'],
            'usdt_micro' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
