<?php

declare(strict_types=1);

namespace Modules\Mall\Listeners;

use Modules\Core\Domain\Events\VehicleOwnershipTransferred;
use Modules\Mall\Domain\Enums\MemberStatus;
use Modules\Mall\Domain\Models\ParkingMember;

class CancelParkingMembershipOnVehicleTransfer
{
    public function handle(VehicleOwnershipTransferred $event): void
    {
        // Nonaktifkan keanggotaan parkir aktif pemilik lama untuk kendaraan yang ditransfer
        ParkingMember::query()
            ->where(function ($q) use ($event) {
                $q->where('vehicle_id', $event->vehicle->id)
                    ->orWhere('plate_number', $event->vehicle->plate_number);
            })
            ->where('user_id', $event->fromUserId)
            ->where('status', MemberStatus::ACTIVE)
            ->update([
                'status' => MemberStatus::CANCELLED,
                'notes' => 'Keanggotaan parkir dibatalkan otomatis karena kepemilikan kendaraan berpindah.',
            ]);
    }
}
