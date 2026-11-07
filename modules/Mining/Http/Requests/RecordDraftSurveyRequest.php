<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordDraftSurveyRequest extends FormRequest
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
            'barge_code' => ['required', 'string', 'max:50'],
            'survey_type' => ['required', 'string', 'in:INITIAL_LIGHT,INTERMEDIATE,FINAL_LOADED'],
            'surveyor_name' => ['required', 'string', 'max:100'],
            'surveyor_company' => ['nullable', 'string', 'max:100'],
            'draft_forward_port_m' => ['required', 'numeric', 'min:0'],
            'draft_forward_starboard_m' => ['required', 'numeric', 'min:0'],
            'draft_midship_port_m' => ['required', 'numeric', 'min:0'],
            'draft_midship_starboard_m' => ['required', 'numeric', 'min:0'],
            'draft_aft_port_m' => ['required', 'numeric', 'min:0'],
            'draft_aft_starboard_m' => ['required', 'numeric', 'min:0'],
            'water_density_measured_kg_l' => ['required', 'numeric', 'between:0.98,1.05'],
            'cargo_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
