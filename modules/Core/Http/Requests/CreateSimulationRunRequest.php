<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class CreateSimulationRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'sim_operator']) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'days' => ['required', 'integer', 'min:1', 'max:1825'],
            'seed' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', 'string', 'in:live,sandbox'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->hasHeader('Idempotency-Key') || trim((string) $this->header('Idempotency-Key')) === '') {
            throw ValidationException::withMessages([
                'idempotency_key' => 'Header Idempotency-Key wajib disertakan untuk setiap aksi mutasi sesi simulasi.',
            ])->status(422);
        }
    }
}
