<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlanBlackStartRequest extends FormRequest
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
            'plan_code' => ['required', 'string', 'max:50'],
            'components' => ['required', 'array', 'min:2'],
            'components.*.code' => ['required', 'string', 'max:50'],
            'components.*.name' => ['required', 'string', 'max:100'],
            'components.*.dependencies' => ['present', 'array'],
            'components.*.step_duration_seconds' => ['required', 'integer', 'min:1'],
            'components.*.inrush_current_amp' => ['required', 'integer', 'min:0'],
        ];
    }
}
