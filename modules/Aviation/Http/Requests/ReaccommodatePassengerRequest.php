<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReaccommodatePassengerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disruption_case_id' => ['required', 'integer', 'exists:avi_disruption_cases,id'],
            'alternative_flight_id' => ['nullable', 'integer', 'exists:avi_flights,id'],
            'force_hotel' => ['nullable', 'boolean'],
        ];
    }
}
