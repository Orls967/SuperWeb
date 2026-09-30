<?php

declare(strict_types=1);

namespace Modules\Logistics\Policies;

use App\Models\User;

class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin()
            || $user->isLogisticsAdmin()
            || $user->isDispatcher()
            || $user->isHubOperator()
            || $user->isDriver()
            || $user->isShipper();
    }

    public function view(User $user, mixed $shipment = null): bool
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher() || $user->isHubOperator()) {
            return true;
        }

        if ($shipment && isset($shipment->shipper_id) && $user->id === $shipment->shipper_id) {
            return true;
        }

        if ($shipment && isset($shipment->driver_id) && $user->id === $shipment->driver_id) {
            return true;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin()
            || $user->isLogisticsAdmin()
            || $user->isDispatcher()
            || $user->isShipper();
    }

    public function update(User $user, mixed $shipment = null): bool
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()) {
            return true;
        }

        if ($shipment && isset($shipment->shipper_id) && $user->id === $shipment->shipper_id) {
            return ($shipment->status ?? '') === 'draft';
        }

        return false;
    }

    public function cancel(User $user, mixed $shipment = null): bool
    {
        if ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()) {
            return true;
        }

        if ($shipment && isset($shipment->shipper_id) && $user->id === $shipment->shipper_id) {
            return in_array($shipment->status ?? '', ['draft', 'booked']);
        }

        return false;
    }
}
