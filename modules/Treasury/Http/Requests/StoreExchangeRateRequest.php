<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreExchangeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'rate_date' => ['required', 'date_format:Y-m-d'],
            'rate_numerator' => ['required', 'integer', 'min:1'],
            'rate_denominator' => ['required', 'integer', 'min:1'],
            'rate_type' => ['required', 'string', 'in:SPOT,CLOSING,TAX,HISTORICAL'],
            'source' => ['required', 'string', 'max:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.required' => 'Mata uang wajib diisi.',
            'rate_date.date_format' => 'Format tanggal kurs harus YYYY-MM-DD.',
            'rate_numerator.min' => 'Numerator kurs rasional harus minimal 1.',
            'rate_denominator.min' => 'Denominator kurs rasional harus minimal 1.',
            'rate_type.in' => 'Tipe kurs harus SPOT, CLOSING, TAX, atau HISTORICAL.',
            'source.required' => 'Sumber kurs (mis. BI, JISDOR, Reuters) wajib diisi.',
        ];
    }

    public function getCurrency(): string
    {
        return strtoupper((string) $this->input('currency'));
    }

    public function getRateDate(): string
    {
        return (string) $this->input('rate_date');
    }

    public function getNumerator(): int
    {
        return (int) $this->input('rate_numerator');
    }

    public function getDenominator(): int
    {
        return (int) $this->input('rate_denominator');
    }

    public function getRateType(): string
    {
        return (string) $this->input('rate_type');
    }

    public function getSource(): string
    {
        return (string) $this->input('source');
    }
}
