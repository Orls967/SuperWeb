<?php

declare(strict_types=1);

namespace Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Finance\Application\Services\LoanSimulator;

class OpenLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Formulir selalu menyertakan kunci tersembunyi; pemanggil programatik tanpa
     * kunci tetap mendapat satu nilai stabil untuk submit ini saja.
     */
    public function prepareForValidation(): void
    {
        if ($this->filled('idempotency_key')) {
            return;
        }

        $this->merge(['idempotency_key' => (string) Str::uuid()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'down_payment' => 'required|integer|min:0',
            'tenor_months' => ['required', 'integer', Rule::in(LoanSimulator::ALLOWED_TENORS)],
            'collateral_symbol' => 'required|string|max:10|exists:crypto_assets,symbol',
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'pin' => 'required|string|size:6',
            'idempotency_key' => 'required|string|max:64',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tenor_months.in' => 'Tenor hanya tersedia untuk 6, 12, 24, atau 36 bulan.',
            'collateral_symbol.exists' => 'Aset kolateral tidak dikenal.',
            'pin.size' => 'PIN dompet harus 6 digit.',
            'idempotency_key.required' => 'Kunci pengajuan tidak ditemukan. Muat ulang halaman lalu coba lagi.',
        ];
    }
}
