<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnforceZoneInterlockRequest extends FormRequest
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
            'warehouse_id' => ['required', 'string', 'max:64'],
            'zone_code' => ['required', 'string', 'max:64'],
            'human_present' => ['required', 'boolean'],
        ];
    }
}
