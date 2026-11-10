<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionForwardContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:designate,activate,mature,settle,terminate,default'],
            'settlement_spot_numerator' => ['required_if:action,settle', 'nullable', 'integer', 'min:1'],
            'settlement_spot_denominator' => ['required_if:action,settle', 'nullable', 'integer', 'min:1'],
            'reason' => ['sometimes', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'action.in' => 'Aksi transisi tidak valid. Pilihan: designate, activate, mature, settle, terminate, default.',
            'settlement_spot_numerator.required_if' => 'Numerator kurs spot penyelesaian wajib diisi saat settlement.',
            'settlement_spot_denominator.required_if' => 'Denominator kurs spot penyelesaian wajib diisi saat settlement.',
        ];
    }

    public function getAction(): string
    {
        return (string) $this->input('action');
    }

    public function getSettlementSpotNumerator(): ?int
    {
        return $this->filled('settlement_spot_numerator') ? (int) $this->input('settlement_spot_numerator') : null;
    }

    public function getSettlementSpotDenominator(): ?int
    {
        return $this->filled('settlement_spot_denominator') ? (int) $this->input('settlement_spot_denominator') : null;
    }

    public function getReason(): string
    {
        return (string) $this->input('reason', '');
    }
}
