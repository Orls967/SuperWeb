<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewDpsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['admin', 'dps_reviewer'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'opinion_note' => ['required', 'string', 'max:255'],
        ];
    }
}
