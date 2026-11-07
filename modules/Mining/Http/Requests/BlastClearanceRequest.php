<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BlastClearanceRequest extends FormRequest
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
            'pattern_id' => ['required', 'string', 'max:50'],
            'pit_name' => ['required', 'string', 'max:100'],
            'center_latitude' => ['required', 'numeric', 'between:-90,90'],
            'center_longitude' => ['required', 'numeric', 'between:-180,180'],
            'safety_radius_meters' => ['required', 'numeric', 'gt:0'],
            'planned_holes_count' => ['required', 'integer', 'gt:0'],
            'total_explosives_kg' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
