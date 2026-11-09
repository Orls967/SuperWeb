<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRequest;

final class EmergencyChangeRetrospectiveTracker
{
    /**
     * Enforces that every emergency production change completes a mandatory retrospective within 48 hours.
     *
     * @return array{requires_retro: bool, is_overdue: bool, hours_remaining: float}
     */
    public function evaluateRetrospectiveRequirement(ChangeRequest $cr): array
    {
        if ($cr->change_type !== 'emergency') {
            return [
                'requires_retro' => false,
                'is_overdue' => false,
                'hours_remaining' => 0.0,
            ];
        }

        $deployedAt = $cr->deployed_at ?? $cr->created_at;
        $deadline = $deployedAt->addHours(48);
        $now = now();

        $isOverdue = ($now > $deadline);
        $hoursRemaining = $isOverdue ? 0.0 : round($now->diffInMinutes($deadline, false) / 60.0, 1);

        return [
            'requires_retro' => true,
            'is_overdue' => $isOverdue,
            'hours_remaining' => max(0.0, $hoursRemaining),
        ];
    }
}
