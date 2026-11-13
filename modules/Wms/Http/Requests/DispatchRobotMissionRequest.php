<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DispatchRobotMissionRequest extends FormRequest
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
            'robot_unit_id' => ['required', 'integer', 'min:1'],
            'zone_code' => ['required', 'string', 'max:64'],
            'mission_code' => ['required', 'string', 'max:64'],
            'wave_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
