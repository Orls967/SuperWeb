<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PostFxTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_account' => ['required', 'string', 'max:64'],
            'to_account' => ['required', 'string', 'max:64', 'different:from_account'],
            'foreign_amount' => ['required', 'integer', 'min:1'],
            'foreign_currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_account.required' => 'Rekening sumber wajib diisi.',
            'to_account.required' => 'Rekening tujuan wajib diisi.',
            'to_account.different' => 'Rekening tujuan harus berbeda dari rekening sumber.',
            'foreign_amount.required' => 'Nominal valas wajib diisi.',
            'foreign_amount.integer' => 'Nominal valas harus bilangan bulat (minor units / cent).',
            'foreign_amount.min' => 'Nominal valas minimal 1 minor unit.',
            'foreign_currency.regex' => 'Format kode mata uang valas harus 3 huruf kapital (ISO 4217).',
            'description.required' => 'Keterangan transaksi wajib disertakan.',
        ];
    }

    public function getFromAccount(): string
    {
        return (string) $this->input('from_account');
    }

    public function getToAccount(): string
    {
        return (string) $this->input('to_account');
    }

    public function getForeignAmount(): int
    {
        return (int) $this->input('foreign_amount');
    }

    public function getForeignCurrency(): string
    {
        return strtoupper((string) $this->input('foreign_currency'));
    }

    public function getDescription(): string
    {
        return (string) $this->input('description');
    }

    public function getIdempotencyKey(): string
    {
        return (string) ($this->header('Idempotency-Key') ?: $this->input('idempotency_key') ?: '');
    }
}
