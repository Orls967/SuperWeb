<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ExecuteCashPoolSweepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_date' => ['required', 'date_format:Y-m-d'],
            'target_balance' => ['sometimes', 'integer', 'min:0'],
            'direction' => ['sometimes', 'string', 'in:TWO_WAY,ONE_WAY_CONCENTRATION,ONE_WAY_DISBURSEMENT'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference_date.required' => 'Tanggal referensi penyapuan kas pool wajib diisi.',
            'reference_date.date_format' => 'Format tanggal referensi harus YYYY-MM-DD.',
            'target_balance.min' => 'Target saldo rekening anak perusahaan tidak boleh negatif.',
        ];
    }

    public function getReferenceDate(): string
    {
        return (string) $this->input('reference_date');
    }

    public function getTargetBalance(): int
    {
        return (int) $this->input('target_balance', 0);
    }

    public function getDirection(): string
    {
        return (string) $this->input('direction', 'TWO_WAY');
    }
}
