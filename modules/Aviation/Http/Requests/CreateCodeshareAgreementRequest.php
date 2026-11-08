<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCodeshareAgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'operating_airline_id' => ['required', 'integer', 'exists:avi_airlines,id'],
            'marketing_airline_id' => ['required', 'integer', 'exists:avi_airlines,id', 'different:operating_airline_id'],
            'agreement_code' => ['required', 'string', 'max:50', 'unique:avi_codeshare_agreements,agreement_code'],
            'agreement_type' => ['required', 'string', 'in:free_sale,block_space_hard,block_space_soft'],
            'revenue_split_bps' => ['required', 'integer', 'min:1000', 'max:9000'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
        ];
    }
}
