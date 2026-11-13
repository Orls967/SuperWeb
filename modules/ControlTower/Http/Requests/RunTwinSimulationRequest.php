<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunTwinSimulationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'shock_type' => ['required', 'string', 'in:PORT_CLOSURE,MATERIAL_INFLATION,PLANT_EXPANSION,DEMAND_SURGE'],
            'shock_magnitude_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'duration_days' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }
}
