<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSmelterFurnaceRunRequest extends FormRequest
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
            'furnace_id' => ['required', 'uuid'],
            'ore_feed_wmt' => ['required', 'numeric', 'gt:0'],
            'ore_moisture_pct' => ['required', 'numeric', 'between:0,70'],
            'ore_ni_grade_pct' => ['required', 'numeric', 'between:0.5,10.0'],
            'ore_fe_grade_pct' => ['required', 'numeric', 'between:5.0,50.0'],
            'coal_reductant_tons' => ['required', 'numeric', 'min:0'],
            'silica_flux_tons' => ['required', 'numeric', 'min:0'],
            'tapping_temperature_c' => ['nullable', 'numeric', 'between:1200,1800'],
            'operator_in_charge' => ['nullable', 'string', 'max:64'],
        ];
    }
}
