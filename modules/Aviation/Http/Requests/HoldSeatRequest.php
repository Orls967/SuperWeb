<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HoldSeatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flight_id' => ['required', 'integer', 'exists:avi_flights,id'],
            'cabin' => ['required', 'string', 'in:Y,J'],
            'seats' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->header('Idempotency-Key')) {
            abort(422, 'Header Idempotency-Key wajib disertakan.');
        }
    }
}

