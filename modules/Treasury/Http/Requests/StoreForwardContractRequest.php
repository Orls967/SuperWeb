<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreForwardContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_number' => ['required', 'string', 'max:64', 'unique:trs_forward_contracts,contract_number'],
            'counterparty_bank' => ['required', 'string', 'max:128'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'notional_amount' => ['required', 'integer', 'min:1'],
            'contract_rate_numerator' => ['required', 'integer', 'min:1'],
            'contract_rate_denominator' => ['required', 'integer', 'min:1'],
            'spot_at_inception_numerator' => ['required', 'integer', 'min:1'],
            'spot_at_inception_denominator' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'maturity_date' => ['required', 'date_format:Y-m-d', 'after:start_date'],
            'hedge_type' => ['required', 'string', 'in:CASH_FLOW_HEDGE,FAIR_VALUE_HEDGE,NET_INVESTMENT'],
            'underlying_exposure' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'contract_number.required' => 'Nomor kontrak forward wajib diisi.',
            'contract_number.unique' => 'Nomor kontrak forward sudah terdaftar.',
            'counterparty_bank.required' => 'Bank lawan transaksi (counterparty) wajib diisi.',
            'notional_amount.min' => 'Nominal nosional harus lebih dari 0.',
            'maturity_date.after' => 'Tanggal jatuh tempo harus setelah tanggal mulai.',
            'hedge_type.in' => 'Tipe lindung nilai harus CASH_FLOW_HEDGE, FAIR_VALUE_HEDGE, atau NET_INVESTMENT.',
        ];
    }

    public function getContractNumber(): string
    {
        return (string) $this->input('contract_number');
    }

    public function getCounterpartyBank(): string
    {
        return (string) $this->input('counterparty_bank');
    }

    public function getCurrency(): string
    {
        return strtoupper((string) $this->input('currency'));
    }

    public function getNotionalAmount(): int
    {
        return (int) $this->input('notional_amount');
    }

    public function getContractRateNumerator(): int
    {
        return (int) $this->input('contract_rate_numerator');
    }

    public function getContractRateDenominator(): int
    {
        return (int) $this->input('contract_rate_denominator');
    }

    public function getSpotAtInceptionNumerator(): int
    {
        return (int) $this->input('spot_at_inception_numerator');
    }

    public function getSpotAtInceptionDenominator(): int
    {
        return (int) $this->input('spot_at_inception_denominator');
    }

    public function getStartDate(): string
    {
        return (string) $this->input('start_date');
    }

    public function getMaturityDate(): string
    {
        return (string) $this->input('maturity_date');
    }

    public function getHedgeType(): string
    {
        return (string) $this->input('hedge_type');
    }

    public function getUnderlyingExposure(): string
    {
        return (string) $this->input('underlying_exposure');
    }
}
