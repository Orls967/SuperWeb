<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUndergroundStopeRequest extends FormRequest
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
            'stope_code' => ['required', 'string', 'max:50'],
            'level_name' => ['required', 'string', 'max:100'],
            'mining_method' => ['nullable', 'string', 'in:SUBLEVEL_OPEN_STOPING,CUT_AND_FILL_STOPING,SQUARE_SET_OR_TOP_SLICING,SHRINKAGE_STOPING'],
            'planned_tonnage' => ['required', 'numeric', 'min:0'],
            'ore_grade' => ['required', 'numeric', 'min:0'],
            'rock_mass_rating' => ['required', 'integer', 'between:0,100'],
            'length_meters' => ['required', 'numeric', 'gt:0'],
            'width_meters' => ['required', 'numeric', 'gt:0'],
            'height_meters' => ['required', 'numeric', 'gt:0'],
            'rock_density_t_m3' => ['nullable', 'numeric', 'gt:0'],
            'support_system' => ['nullable', 'string', 'max:255'],
        ];
    }
}
