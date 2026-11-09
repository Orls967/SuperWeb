<?php

declare(strict_types=1);

namespace Modules\Hospital\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateBillingEpisodeRequest extends FormRequest
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
            'encounter_id' => ['required', 'integer', 'exists:hsp_encounters,id'],
            'initial_deposit_idr' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
