<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IssueLoanRequest extends FormRequest
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
            'lender_entity' => ['required', 'string', 'max:32', 'different:borrower_entity'],
            'borrower_entity' => ['required', 'string', 'max:32'],
            'principal_idr' => ['required', 'integer', 'min:1000000'],
            'base_rate_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            'spread_bps' => ['required', 'integer', 'min:0', 'max:5000'],
            'withholding_tax_bps' => ['required', 'integer', 'min:0', 'max:3000'],
            'periods' => ['required', 'integer', 'min:1', 'max:120'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:start_date'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lender_entity.different' => 'Entitas pemberi dan penerima pinjaman tidak boleh sama.',
            'principal_idr.min' => 'Pokok pinjaman minimal Rp 1.000.000.',
            'due_date.after' => 'Tanggal jatuh tempo harus setelah tanggal mulai pinjaman.',
        ];
    }
}
