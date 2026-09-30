<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Mall\Domain\Enums\VehicleType;

class CheckInVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isMallAdmin());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'integer', 'exists:mall_properties,id'],
            'plate_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9 \-]+$/'],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'parking_zone_id' => ['nullable', 'integer', 'exists:mall_parking_zones,id'],
            'entry_gate' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plate_number.regex' => 'Plat nomor hanya boleh berisi huruf, angka, spasi, dan tanda hubung.',
            'plate_number.required' => 'Plat nomor kendaraan wajib diisi.',
            'vehicle_type.required' => 'Jenis kendaraan wajib dipilih.',
        ];
    }
}
