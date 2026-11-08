<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hold_id' => ['required', 'integer', 'exists:avi_seat_holds,id'],
            'passenger_names' => ['required', 'array', 'min:1'],
            'passenger_names.*' => ['required', 'string', 'max:128'],
            'passenger_party_ids' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->header('Idempotency-Key')) {
            abort(422, 'Header Idempotency-Key wajib disertakan.');
        }
    }
}

