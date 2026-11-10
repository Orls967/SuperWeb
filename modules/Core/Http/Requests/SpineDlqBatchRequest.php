<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi Permintaan Batch Operasi DLQ (SpineDlqBatchRequest)
 */
class SpineDlqBatchRequest extends FormRequest
{
    /**
     * Menentukan apakah pengguna diotorisasi untuk melakukan request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk batch request DLQ.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', 'in:replay,discard,quarantine'],
            'delivery_ids' => ['required', 'array', 'min:1'],
            'delivery_ids.*' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:500'],
            'approval_token' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Pesan kesalahan khusus dalam bahasa Indonesia.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Aksi penanganan DLQ wajib dipilih.',
            'action.in' => 'Aksi penanganan DLQ harus salah satu dari: replay, discard, atau quarantine.',
            'delivery_ids.required' => 'Daftar ID pesan DLQ wajib disertakan.',
            'delivery_ids.min' => 'Minimal satu ID pesan DLQ harus dipilih.',
            'reason.max' => 'Alasan pembuangan/tindakan maksimal 500 karakter.',
        ];
    }
}

