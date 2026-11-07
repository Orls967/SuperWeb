<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueWarehouseReceiptRequest extends FormRequest
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
            'assay_id' => ['required', 'uuid'],
            'certified_weight_metric_tons' => ['required', 'numeric', 'gt:0'],
            'unit_market_price_idr_per_ton' => ['nullable', 'integer', 'gt:0'],
            'warehouse_code' => ['nullable', 'string', 'max:32'],
            'warehouse_operator_name' => ['nullable', 'string', 'max:128'],
            'storage_location_rack' => ['nullable', 'string', 'max:64'],
        ];
    }
}
