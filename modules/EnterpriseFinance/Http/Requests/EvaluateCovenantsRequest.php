<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateCovenantsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'max:16'],
            'ebitda_minor' => ['required', 'integer', 'min:0'],
            'tax_paid_minor' => ['required', 'integer', 'min:0'],
            'debt_service_minor' => ['required', 'integer', 'min:1'],
            'total_debt_minor' => ['required', 'integer', 'min:0'],
            'total_equity_minor' => ['required', 'integer', 'min:1'],
            'ebit_minor' => ['required', 'integer', 'min:0'],
            'interest_expense_minor' => ['required', 'integer', 'min:1'],
            'current_assets_minor' => ['required', 'integer', 'min:0'],
            'current_liabilities_minor' => ['required', 'integer', 'min:1'],
        ];
    }
}
