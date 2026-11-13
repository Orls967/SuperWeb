<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ApproveRoboticsRoiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'auditor']) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'justification' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
