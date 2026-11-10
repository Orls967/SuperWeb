<?php

declare(strict_types=1);

namespace Modules\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi Permintaan Replay Aliran Stream Event (SpineReplayStreamRequest)
 */
class SpineReplayStreamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'topic' => ['nullable', 'string', 'max:150'],
            'from_offset' => ['required', 'integer', 'min:0'],
            'to_offset' => ['nullable', 'integer', 'gte:from_offset'],
            'consumer_group' => ['nullable', 'string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_offset.required' => 'Offset awal stream (from_offset) wajib ditentukan.',
            'from_offset.min' => 'Offset awal tidak boleh bernilai negatif.',
            'to_offset.gte' => 'Offset akhir (to_offset) harus lebih besar atau sama dengan offset awal.',
            'limit.max' => 'Batas maksimal pembacaan per transaksi adalah 5000 pesan.',
        ];
    }
}

