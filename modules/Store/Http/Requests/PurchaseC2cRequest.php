<?php

declare(strict_types=1);

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseC2cRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'pin' => 'required|string|size:6',
            'idempotency_key' => 'nullable|uuid',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.size' => 'PIN dompet harus 6 digit.',
        ];
    }
}
