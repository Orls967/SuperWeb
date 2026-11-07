<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SlopeTelemetryRequest extends FormRequest
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
            'location_sector' => ['required', 'string', 'max:100'],
            'radar_unit_id' => ['required', 'string', 'max:50'],
            'displacement_rate_mm_hour' => ['required', 'numeric', 'min:0'],
            'cumulative_displacement_mm' => ['required', 'numeric', 'min:0'],
            'factor_of_safety_fos' => ['nullable', 'numeric', 'gt:0'],
            'pore_water_pressure_kpa' => ['nullable', 'numeric'],
            'crack_aperture_mm' => ['nullable', 'numeric', 'min:0'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'source_sensor_type' => ['nullable', 'string', 'max:50'],
        ];
    }
}
