<?php

declare(strict_types=1);

namespace Modules\TradeFinance\Http\Policies;

use App\Models\User;
use Modules\TradeFinance\Domain\Models\Clearing\ClearingEscrow;

class ClearingEscrowPolicy
{
    /** Petugas melihat semua; importir/eksportir hanya escrow miliknya (anti-IDOR). */
    public function view(User $user, ClearingEscrow $escrow): bool
    {
        return in_array($user->role, ['admin', 'tf_officer'], true) || $escrow->isParty((int) $user->id);
    }
}
