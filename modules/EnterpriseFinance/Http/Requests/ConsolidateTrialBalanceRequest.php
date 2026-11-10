<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsolidateTrialBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'reporting_currency' => ['nullable', 'string', 'size:3'],
            'entities' => ['required', 'array', 'min:1'],
            'entities.*.entity_code' => ['required', 'string', 'max:32'],
            'entities.*.entity_name' => ['required', 'string', 'max:128'],
            'entities.*.currency' => ['nullable', 'string', 'size:3'],
            'entities.*.accounts' => ['required', 'array', 'min:1'],
            'entities.*.accounts.*.account_code' => ['required', 'string', 'max:32'],
            'entities.*.accounts.*.account_name' => ['required', 'string', 'max:128'],
            'entities.*.accounts.*.debit' => ['required', 'integer', 'min:0'],
            'entities.*.accounts.*.credit' => ['required', 'integer', 'min:0'],
        ];
    }
}
