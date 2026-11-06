<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MintRecRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'generator_asset_code' => ['required', 'string', 'max:50'],
            'generation_period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'mwh_amount' => ['required', 'integer', 'min:1'],
            'registry_type' => ['required', 'string', 'in:I_REC,TIGR,PLN_REC'],
            'vintage_year' => ['required', 'integer', 'min:2020', 'max:2030'],
        ];
    }
}
