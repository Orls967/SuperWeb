<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitDemandResponseRequest extends FormRequest
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
            'event_code' => ['required', 'string', 'max:50'],
            'curtailment_signal_id' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date'],
            'target_curtailment_kw' => ['required', 'integer', 'min:10'],
            'achieved_curtailment_kw' => ['required', 'integer', 'min:0'],
            'response_time_seconds' => ['required', 'integer', 'min:1'],
        ];
    }
}
