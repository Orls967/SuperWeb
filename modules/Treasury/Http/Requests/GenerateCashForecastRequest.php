<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GenerateCashForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'horizon_weeks' => ['sometimes', 'integer', 'in:4,13,26,52'],
            'season_period' => ['sometimes', 'integer', 'in:4,12,13'],
        ];
    }

    public function messages(): array
    {
        return [
            'currency.required' => 'Mata uang peramalan kas wajib diisi.',
            'start_date.required' => 'Tanggal awal horizon peramalan kas wajib diisi.',
            'horizon_weeks.in' => 'Horizon peramalan harus 4, 13, 26, atau 52 minggu.',
            'season_period.in' => 'Periode musiman harus 4, 12, atau 13 minggu.',
        ];
    }

    public function getCurrency(): string
    {
        return strtoupper((string) $this->input('currency'));
    }

    public function getStartDate(): string
    {
        return (string) $this->input('start_date');
    }

    public function getHorizonWeeks(): int
    {
        return (int) $this->input('horizon_weeks', 13);
    }

    public function getSeasonPeriod(): int
    {
        return (int) $this->input('season_period', 4);
    }
}
