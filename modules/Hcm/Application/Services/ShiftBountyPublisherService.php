<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services;

use Carbon\Carbon;
use Modules\Hcm\Application\Services\Gig\InternalGigEconomyService;
use Modules\Hcm\Contracts\ShiftBountyPublisher;

final class ShiftBountyPublisherService implements ShiftBountyPublisher
{
    public function __construct(
        private readonly InternalGigEconomyService $gigService
    ) {}

    public function publishBounty(
        string $costCenter,
        string $title,
        string $description,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        int $hourlyRateIdr,
        ?string $requiredCertification = null,
        bool $requiresHighRiskPermit = false,
        int $speedBonusIdr = 0
    ): array {
        $bounty = $this->gigService->postBounty(
            costCenter: $costCenter,
            title: $title,
            description: $description,
            shiftStart: $shiftStart,
            shiftEnd: $shiftEnd,
            hourlyRateIdr: $hourlyRateIdr,
            requiredCertification: $requiredCertification,
            requiresHighRiskPermit: $requiresHighRiskPermit,
            speedBonusIdr: $speedBonusIdr
        );

        return [
            'bounty_id' => $bounty->id,
            'bounty_code' => $bounty->bounty_code,
            'insurance_ref' => $bounty->insurance_ref,
            'cost_center' => $bounty->cost_center,
            'hourly_rate_idr' => (int) $bounty->hourly_rate_idr,
            'status' => (string) $bounty->status,
        ];
    }
}
