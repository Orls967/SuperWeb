<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReserveRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        return in_array($user->role, [
            'admin',
            'hotel_manager',
            'front_office',
            'reservation_agent',
            'front_desk',
            'hotel_admin',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => 'required',
            'room_type_id' => 'required',
            'rate_plan_id' => 'required',
            'check_in_date' => 'required|date',
            'check_out_date' => 'required|date|after:check_in_date',
            'guest_user_id' => 'required',
            'idempotency_key' => 'required|string|max:64',
            'room_id' => 'nullable',
            'deposit_amount_idr' => 'nullable|integer|min:0',
        ];
    }
}
