<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunFxRevaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'closing_rate_numerator' => ['required', 'integer', 'min:1'],
            'closing_rate_denominator' => ['required', 'integer', 'min:1'],
            'auto_reverse_next_period' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'period.regex' => 'Format periode harus YYYY-MM (misal: 2026-11).',
            'currency.size' => 'Mata uang harus 3 karakter ISO 4217.',
            'closing_rate_numerator.min' => 'Numerator kurs penutupan harus positif.',
            'closing_rate_denominator.min' => 'Denominator kurs penutupan harus positif.',
        ];
    }

    public function getPeriod(): string
    {
        return (string) $this->input('period');
    }

    public function getCurrency(): string
    {
        return strtoupper((string) $this->input('currency'));
    }

    public function getClosingNumerator(): int
    {
        return (int) $this->input('closing_rate_numerator');
    }

    public function getClosingDenominator(): int
    {
        return (int) $this->input('closing_rate_denominator');
    }

    public function shouldAutoReverse(): bool
    {
        return (bool) $this->input('auto_reverse_next_period', true);
    }
}
