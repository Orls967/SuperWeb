<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteSmelterBalanceRequest extends FormRequest
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
            'run_id' => ['required', 'uuid'],
            'power_consumption_mwh' => ['nullable', 'numeric', 'gt:0'],
            'target_recovery_pct' => ['nullable', 'numeric', 'between:70,99'],
        ];
    }
}
