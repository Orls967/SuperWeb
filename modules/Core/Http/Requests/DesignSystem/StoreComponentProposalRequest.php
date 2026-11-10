<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\DesignSystem;

use Illuminate\Foundation\Http\FormRequest;

final class StoreComponentProposalRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/', 'unique:core_ds_components,name'],
            'display_name' => ['required', 'string', 'max:128'],
            'category' => ['required', 'string', 'in:foundation,layout,form,navigation,feedback,data-display,action'],
            'wcag_level' => ['required', 'string', 'in:A,AA,AAA'],
            'spec_doc_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'props_schema' => ['nullable', 'array'],
            'slots_schema' => ['nullable', 'array'],
            'events_schema' => ['nullable', 'array'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ];
    }
}
