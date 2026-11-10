<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SpendBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_idr' => ['required', 'integer', 'min:1'],
            'reference_no' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
