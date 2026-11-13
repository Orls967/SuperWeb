<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWavePickingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'wave_reference' => ['required', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty_base' => ['required', 'integer', 'min:1'],
            'lines.*.unit_cost_idr' => ['required', 'integer', 'min:0'],
        ];
    }
}
