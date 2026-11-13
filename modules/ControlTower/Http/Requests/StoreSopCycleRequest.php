<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSopCycleRequest extends FormRequest
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
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'scenario_type' => ['required', 'string', 'in:conservative,moderate,aggressive'],
            'demand_plan_qty' => ['required', 'integer', 'min:0'],
            'supply_plan_qty' => ['required', 'integer', 'min:0'],
            'capacity_utilization_bps' => ['required', 'integer', 'min:0', 'max:20000'],
            'gross_margin_est_idr' => ['required', 'integer'],
            'cash_impact_est_idr' => ['required', 'integer'],
        ];
    }
}
