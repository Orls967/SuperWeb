<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ReconcileBankStatementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_account_id' => ['required', 'string', 'max:64'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'date_tolerance_days' => ['sometimes', 'integer', 'min:0', 'max:14'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_account_id.required' => 'Rekening bank wajib dipilih.',
            'start_date.required' => 'Tanggal awal periode rekonsiliasi wajib diisi.',
            'end_date.after_or_equal' => 'Tanggal akhir harus sama atau setelah tanggal awal.',
            'date_tolerance_days.max' => 'Toleransi tanggal maksimal 14 hari kerja.',
        ];
    }

    public function getBankAccountId(): string
    {
        return (string) $this->input('bank_account_id');
    }

    public function getStartDate(): string
    {
        return (string) $this->input('start_date');
    }

    public function getEndDate(): string
    {
        return (string) $this->input('end_date');
    }

    public function getDateToleranceDays(): int
    {
        return (int) $this->input('date_tolerance_days', 3);
    }
}
