<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\DesignSystem;

use Illuminate\Foundation\Http\FormRequest;

final class TransitionComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to_status' => ['required', 'string', 'in:draft,in_review,approved,released,deprecated,retired,archived'],
            'reason' => ['nullable', 'string', 'max:500'],
            'new_version' => ['nullable', 'string', 'max:32'],
            'breaking_changes' => ['nullable', 'boolean'],
            'a11y_checklist_passed' => ['nullable', 'boolean'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
