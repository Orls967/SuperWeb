<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreForecastOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason') && is_string($this->input('reason'))) {
            $this->merge([
                'reason' => trim($this->input('reason')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'demand_forecast_id' => ['required', 'integer', 'exists:sct_demand_forecasts,id'],
            'new_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
