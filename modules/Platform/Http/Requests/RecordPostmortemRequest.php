<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordPostmortemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'incident_ref' => ['required', 'string', 'max:64', 'unique:plt_postmortems,incident_ref'],
            'title' => ['required', 'string', 'min:5', 'max:128'],
            'severity' => ['required', 'string', 'in:sev1_critical,sev2_major,sev3_minor'],
            'root_cause' => ['required', 'string', 'min:20'],
            'action_items' => ['nullable', 'array'],
        ];
    }
}
