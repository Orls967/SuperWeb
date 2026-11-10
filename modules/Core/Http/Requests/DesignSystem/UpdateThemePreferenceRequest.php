<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests\DesignSystem;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateThemePreferenceRequest extends FormRequest
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
            'theme' => ['required', 'string', 'in:light,dark,hc'],
            'density' => ['nullable', 'string', 'in:compact,comfortable,spacious'],
            'font_scale' => ['nullable', 'integer', 'between:80,150'],
            'reduced_motion' => ['nullable', 'boolean'],
            'high_contrast' => ['nullable', 'boolean'],
        ];
    }
}

