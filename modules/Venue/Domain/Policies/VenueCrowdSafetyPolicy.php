<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Policies;

use App\Models\User;

final class VenueCrowdSafetyPolicy
{
    /**
     * Tentukan apakah pengguna berhak memicu penguncian gerbang darurat.
     */
    public function triggerLockdown(User $user): bool
    {
        return in_array($user->role, ['admin', 'crowd_safety_officer', 'venue_manager'], true);
    }

    /**
     * Tentukan apakah pengguna berhak mengakses live video feed surveillance / telemetri.
     */
    public function viewSurveillanceTelemetry(User $user): bool
    {
        return in_array($user->role, ['admin', 'crowd_safety_officer', 'security_head', 'venue_manager'], true);
    }

    /**
     * Tentukan apakah pengguna berhak melakukan override manual pintu biometrik di kios turnstile.
     */
    public function overrideBiometricGate(User $user): bool
    {
        return in_array($user->role, ['admin', 'venue_staff', 'gate_supervisor'], true);
    }

    /**
     * Tentukan apakah pengguna berhak memanggil shift reinforcement bartender via HCM.
     */
    public function dispatchBarReinforcement(User $user): bool
    {
        return in_array($user->role, ['admin', 'venue_manager', 'bar_supervisor'], true);
    }
}
