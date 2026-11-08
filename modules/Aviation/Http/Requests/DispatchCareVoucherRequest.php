<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchCareVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'disruption_case_id' => ['required', 'integer', 'exists:avi_disruption_cases,id'],
            'override_voucher_type' => ['nullable', 'string', 'in:refreshment,snack,meal,cash_compensation,hotel_stay'],
        ];
    }
}
