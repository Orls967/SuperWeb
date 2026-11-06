<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DispatchBessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tariff_profile' => ['required', 'array', 'size:24'],
            'tariff_profile.*' => ['required', 'integer', 'min:100'],
            'dispatch_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
