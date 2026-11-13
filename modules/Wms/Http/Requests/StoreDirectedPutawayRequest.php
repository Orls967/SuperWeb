<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDirectedPutawayRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'min:1'],
            'qty_base' => ['required', 'integer', 'min:1'],
            'abc_class' => ['required', 'in:A,B,C'],
            'unit_cost_idr' => ['required', 'integer', 'min:0'],
            'reference_id' => ['required', 'string', 'max:64'],
            'lot_number' => ['nullable', 'string', 'max:64'],
            'expiry_date' => ['nullable', 'date'],
        ];
    }
}
