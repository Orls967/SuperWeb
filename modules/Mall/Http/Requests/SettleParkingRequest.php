<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;

class SettleParkingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isMallAdmin());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in([
                ParkingPaymentMethod::CASH->value,
                ParkingPaymentMethod::WALLET->value,
            ])],
            'cash_tendered' => ['nullable', 'integer', 'min:0', 'required_if:payment_method,cash'],
            'payer_email' => ['nullable', 'email', 'required_if:payment_method,wallet', 'exists:users,email'],
            'pin' => ['nullable', 'string', 'digits:6', 'required_if:payment_method,wallet'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_tendered.required_if' => 'Nominal uang tunai yang diterima wajib diisi.',
            'payer_email.required_if' => 'Email pemilik dompet wajib diisi untuk pembayaran non-tunai.',
            'payer_email.exists' => 'Pengguna dengan email tersebut tidak ditemukan.',
            'pin.required_if' => 'PIN dompet wajib diisi untuk pembayaran non-tunai.',
            'pin.digits' => 'PIN dompet harus 6 digit angka.',
        ];
    }
}
