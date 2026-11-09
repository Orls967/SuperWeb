<?php

declare(strict_types=1);

namespace Modules\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfigureSloRequest extends FormRequest
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
            'service_name' => ['required', 'string', 'min:3', 'max:64'],
            'tier' => ['required', 'string', 'in:tier_1_critical,tier_2_high,tier_3_standard'],
            'target_sli_bps' => ['required', 'integer', 'min:9000', 'max:9999'],
        ];
    }
}
