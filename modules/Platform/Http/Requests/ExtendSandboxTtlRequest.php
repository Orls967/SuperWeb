<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExtendSandboxTtlRequest extends FormRequest
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
            'hours' => ['required', 'integer', 'min:1', 'max:72'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ];
    }
}
