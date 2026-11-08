<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDisruptionCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'integer', 'exists:avi_flights,id'],
            'incident_category' => ['required', 'string', 'in:AOG_TECHNICAL,CREW_FTL,FORCE_MAJEURE_WEATHER,FORCE_MAJEURE_VOLCANIC_ERUPTION,ATC_RESTRICTION,AIRPORT_INFRASTRUCTURE,CANCELLATION'],
            'delay_minutes' => ['required', 'integer', 'min:0', 'max:2880'],
            'incident_description' => ['required', 'string', 'min:5', 'max:2000'],
            'alternative_flight_id' => ['nullable', 'integer', 'exists:avi_flights,id'],
        ];
    }
}
