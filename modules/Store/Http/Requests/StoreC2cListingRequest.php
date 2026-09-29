<?php

declare(strict_types=1);

namespace Modules\Store\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreC2cListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'vehicle_id' => 'required|integer|exists:core_vehicles,id',
            'price' => 'required|integer|min:1000000|max:50000000000',
            'description' => 'nullable|string|max:2000',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'vehicle_id.required' => 'Pilih kendaraan yang ingin dijual.',
            'price.min' => 'Harga jual minimal Rp 1.000.000.',
        ];
    }
}
