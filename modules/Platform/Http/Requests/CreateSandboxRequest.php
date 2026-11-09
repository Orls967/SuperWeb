<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSandboxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9\s\-_]+$/'],
            'tier' => ['required', 'string', 'in:standard,ephemeral,isolated'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
