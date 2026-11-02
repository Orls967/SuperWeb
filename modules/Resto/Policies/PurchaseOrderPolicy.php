<?php

declare(strict_types=1);

namespace Modules\Resto\Policies;

use App\Models\User;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\RestoStaffAssignment;

class PurchaseOrderPolicy
{
    public function view(User $user, PurchaseOrder $purchase): bool
    {
        return $this->canAccessOutlet($user, (int) $purchase->outlet_id);
    }

    public function create(User $user, int $outletId): bool
    {
        return $this->canAccessOutlet($user, $outletId);
    }

    public function receive(User $user, PurchaseOrder $purchase): bool
    {
        return $this->canAccessOutlet($user, (int) $purchase->outlet_id);
    }

    public function pay(User $user, PurchaseOrder $purchase): bool
    {
        return $this->canAccessOutlet($user, (int) $purchase->outlet_id);
    }

    private function canAccessOutlet(User $user, ?int $outletId): bool
    {
        if (! in_array($user->role, ['outlet_manager', 'kitchen'], true)) {
            return true;
        }

        if (! $outletId) {
            return false;
        }

        return RestoStaffAssignment::where('user_id', $user->id)
            ->where('outlet_id', $outletId)
            ->where('is_active', true)
            ->exists();
    }
}
