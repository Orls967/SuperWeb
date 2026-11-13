<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PlanRoboticsWaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'hub_operator', 'logistics_admin']) ?? false;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'string', 'exists:wms_warehouses,id'],
            'capacity' => ['required', 'integer', 'min:1', 'max:10000'],
            'batch_code' => ['required', 'string', 'max:48'],
            'idempotency_key' => ['required', 'string', 'max:128'],
        ];
    }
}
