<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteTaskActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:approve,reject,delegate'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delegatee_id' => ['required_if:action,delegate', 'nullable', 'string', 'exists:users,id'],
        ];
    }
}
