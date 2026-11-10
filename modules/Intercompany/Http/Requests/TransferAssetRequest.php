<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetRequest extends FormRequest
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
            'transferor_entity' => ['required', 'string', 'max:32', 'different:transferee_entity'],
            'transferee_entity' => ['required', 'string', 'max:32'],
            'asset_name' => ['required', 'string', 'max:128'],
            'book_value_idr' => ['required', 'integer', 'min:0'],
            'transfer_price_idr' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transferor_entity.different' => 'Entitas asal dan tujuan transfer aset tidak boleh sama.',
        ];
    }
}
