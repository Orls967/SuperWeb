<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectTazirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'syariah_officer'], true);
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('idempotency_key') && $this->headers->has('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->headers->get('Idempotency-Key')]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount_idr' => ['required', 'integer', 'min:1000'],
            'reason' => ['required', 'string', 'max:191'],
            'idempotency_key' => ['required', 'string', 'max:64'],
        ];
    }
}
