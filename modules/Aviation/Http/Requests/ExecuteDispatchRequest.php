<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExecuteDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fuel_weight_kg' => ['required', 'integer', 'min:1000'],
            'cargo_weight_kg' => ['required', 'integer', 'min:0'],
            'pax_count' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
