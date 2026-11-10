<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProcessPettyCashVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'voucher_number' => ['required', 'string', 'max:64', 'unique:trs_petty_cash_vouchers,voucher_number'],
            'action_type' => ['required', 'string', 'in:DISBURSEMENT,REPLENISHMENT,ADJUSTMENT'],
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'expense_category' => ['required_if:action_type,DISBURSEMENT', 'nullable', 'string', 'max:128'],
            'description' => ['required', 'string', 'min:5', 'max:255'],
            'receipt_reference' => ['sometimes', 'nullable', 'string', 'max:128'],
            'payee_name' => ['sometimes', 'nullable', 'string', 'max:128'],
        ];
    }

    public function messages(): array
    {
        return [
            'voucher_number.required' => 'Nomor voucher kas kecil wajib diisi.',
            'voucher_number.unique' => 'Nomor voucher kas kecil sudah terdaftar.',
            'action_type.in' => 'Tipe aksi harus DISBURSEMENT, REPLENISHMENT, atau ADJUSTMENT.',
            'amount.min' => 'Nominal voucher kas kecil minimal 1 minor unit.',
            'expense_category.required_if' => 'Kategori biaya wajib diisi untuk pencairan kas kecil.',
        ];
    }

    public function getVoucherNumber(): string
    {
        return (string) $this->input('voucher_number');
    }

    public function getActionType(): string
    {
        return (string) $this->input('action_type');
    }

    public function getAmount(): int
    {
        return (int) $this->input('amount');
    }

    public function getCurrency(): string
    {
        return strtoupper((string) $this->input('currency'));
    }

    public function getExpenseCategory(): ?string
    {
        return $this->input('expense_category');
    }

    public function getDescription(): string
    {
        return (string) $this->input('description');
    }

    public function getReceiptReference(): ?string
    {
        return $this->input('receipt_reference');
    }

    public function getPayeeName(): ?string
    {
        return $this->input('payee_name');
    }
}
