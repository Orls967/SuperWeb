<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEstimateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && in_array($user->role, ['admin', 'mekanik'], true);
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|in:service,part',
            'items.*.ref_id' => 'nullable|integer',
            'items.*.name' => 'nullable|string|max:255',
            'items.*.qty' => 'required|integer|min:1|max:999',
            'items.*.unit_price' => 'nullable|integer|min:0',
            'send_now' => 'nullable|boolean',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Estimasi harus memuat minimal satu baris jasa atau sparepart.',
            'items.*.qty.min' => 'Jumlah item minimal 1.',
        ];
    }
}
