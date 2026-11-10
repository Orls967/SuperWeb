<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CostSharingAgreementRequest extends FormRequest
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
            'agreement_code' => ['required', 'string', 'max:32', 'unique:ic_cost_sharing_agreements,agreement_code'],
            'service_type' => ['required', 'string', 'max:64'],
            'provider_entity' => ['required', 'string', 'max:32'],
            'total_cost_pool_idr' => ['required', 'integer', 'min:1'],
            'markup_bps' => ['required', 'integer', 'min:0', 'max:3000'],
            'effective_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after:effective_date'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
