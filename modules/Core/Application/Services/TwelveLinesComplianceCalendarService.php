<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use DateTimeInterface;
use Modules\Core\Domain\Models\CoreComplianceObligation;

class TwelveLinesComplianceCalendarService
{
    /**
     * @return list<array{permit: string, level: string, days_left?: int}>
     */
    public function checkExpiringPermits(DateTimeInterface $referenceDate): array
    {
        $permits = CoreComplianceObligation::query()->get();
        $alerts = [];

        foreach ($permits as $permit) {
            $diffDays = (int) $referenceDate->diff($permit->expiration_date)->format('%r%a');

            if ($diffDays <= 0) {
                $permit->update(['status' => 'EXPIRED']);
                $alerts[] = ['permit' => $permit->permit_code, 'level' => 'CRITICAL_EXPIRED'];
            } elseif ($diffDays <= $permit->alert_lead_days) {
                $permit->update(['status' => 'EXPIRING_SOON']);
                $alerts[] = ['permit' => $permit->permit_code, 'level' => 'WARNING_EXPIRING_SOON', 'days_left' => $diffDays];
            }
        }

        return $alerts;
    }
}
