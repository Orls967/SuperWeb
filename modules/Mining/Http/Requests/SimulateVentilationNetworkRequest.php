<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimulateVentilationNetworkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'simulation_code' => ['required', 'string', 'max:50'],
            'fan_rpm_percent' => ['nullable', 'numeric', 'between:10,120'],
            'air_density_kg_m3' => ['nullable', 'numeric', 'between:0.8,1.6'],
            'branches' => ['required', 'array', 'min:1'],
            'branches.*.id' => ['required', 'string'],
            'branches.*.name' => ['required', 'string'],
            'branches.*.resistance_r' => ['required', 'numeric', 'gt:0'],
            'branches.*.initial_flow_q' => ['required', 'numeric'],
            'loops' => ['required', 'array', 'min:1'],
            'loops.*.loop_id' => ['required', 'string'],
            'loops.*.branch_ids' => ['required', 'array'],
            'loops.*.directions' => ['required', 'array'],
            'headcount_underground' => ['nullable', 'integer', 'min:0'],
            'diesel_fleet_kw' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
