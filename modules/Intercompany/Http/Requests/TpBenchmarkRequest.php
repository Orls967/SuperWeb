<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TpBenchmarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tp_rule_id' => ['required', 'integer', 'exists:ic_transfer_pricing_rules,id'],
            'comparable_company_name' => ['required', 'string', 'max:128'],
            'country_code' => ['required', 'string', 'size:2'],
            'benchmark_margin_bps' => ['required', 'integer', 'min:0', 'max:10000'],
            'fiscal_year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'database_source' => ['required', 'string', 'max:64'],
        ];
    }
}
