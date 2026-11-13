<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OverrideForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'demand_forecast_id' => ['required', 'integer', 'exists:sct_demand_forecasts,id'],
            'new_qty' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
