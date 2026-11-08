<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            'hours_before_flight' => ['nullable', 'integer'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->header('Idempotency-Key')) {
            abort(422, 'Header Idempotency-Key wajib disertakan.');
        }
    }
}

