<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInboundReceivingRequest extends FormRequest
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
            'receipt_number' => ['required', 'string', 'max:64'],
            'warehouse_id' => ['required', 'integer', 'min:1'],
            'bin_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['required', 'integer', 'min:1'],
            'expected_qty_base' => ['required', 'integer', 'min:1'],
            'received_qty_base' => ['required', 'integer', 'min:0'],
            'unit_cost_idr' => ['required', 'integer', 'min:0'],
            'lot_number' => ['nullable', 'string', 'max:64'],
            'expiry_date' => ['nullable', 'date'],
            'approve_over_receipt' => ['sometimes', 'boolean'],
        ];
    }
}
