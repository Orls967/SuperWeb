<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferStockRequest extends FormRequest
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
            'source_entity' => ['required', 'string', 'max:32', 'different:destination_entity'],
            'destination_entity' => ['required', 'string', 'max:32'],
            'item_sku' => ['required', 'string', 'max:64'],
            'quantity' => ['required', 'integer', 'min:1'],
            'cost_price_idr' => ['required', 'integer', 'min:1'],
            'transfer_price_idr' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'shipping_manifest_ref' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_entity.different' => 'Entitas pengirim dan penerima transfer stok tidak boleh sama.',
            'quantity.min' => 'Jumlah barang yang ditransfer minimal 1 unit.',
        ];
    }
}
