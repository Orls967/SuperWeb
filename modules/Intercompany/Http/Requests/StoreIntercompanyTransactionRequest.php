<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreIntercompanyTransactionRequest extends FormRequest
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
            'selling_entity' => ['required', 'string', 'max:32', 'different:buying_entity'],
            'buying_entity' => ['required', 'string', 'max:32'],
            'description' => ['required', 'string', 'max:255'],
            'amount_idr' => ['required', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'tp_rule_id' => ['nullable', 'integer', 'exists:ic_transfer_pricing_rules,id'],
            'sales_invoice_ref' => ['nullable', 'string', 'max:64'],
            'purchase_bill_ref' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'selling_entity.different' => 'Entitas penjual dan pembeli tidak boleh sama (pelanggaran transaksi bilateral).',
            'amount_idr.min' => 'Nominal transaksi harus berupa bilangan bulat positif lebih besar dari nol.',
        ];
    }
}
