<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AllocateCostSharingRequest extends FormRequest
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
            'agreement_id' => ['required', 'integer', 'exists:ic_cost_sharing_agreements,id'],
            'beneficiary_entity' => ['required', 'string', 'max:32'],
            'allocated_amount_idr' => ['required', 'integer', 'min:1'],
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'basis_ratio_permille' => ['required', 'integer', 'min:1', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
