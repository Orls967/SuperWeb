<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessWaterfallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'billing_episode_id' => ['required', 'integer', 'exists:hsp_billing_episodes,id'],
            'primary_icd10' => ['required', 'string', 'max:16'],
            'secondary_icd10' => ['nullable', 'array'],
            'secondary_icd10.*' => ['string', 'max:16'],
            'procedures_icd9' => ['nullable', 'array'],
            'procedures_icd9.*' => ['string', 'max:16'],
            'entitled_class' => ['required', 'string', 'in:CLASS_1,CLASS_2,CLASS_3'],
            'actual_class' => ['required', 'string', 'in:CLASS_1,CLASS_2,CLASS_3,VIP,VVIP,EXECUTIVE'],
            'private_insurance_policy_no' => ['nullable', 'string', 'max:64'],
            'guarantee_letter_no' => ['nullable', 'string', 'max:64'],
        ];
    }
}
