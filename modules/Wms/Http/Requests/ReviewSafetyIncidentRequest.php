<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReviewSafetyIncidentRequest extends FormRequest
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
            'decision_status' => ['required', 'string', 'in:reviewed,closed'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
