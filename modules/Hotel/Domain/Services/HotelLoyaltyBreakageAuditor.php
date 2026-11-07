<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Modules\Hotel\Application\Actions\RecognizeLoyaltyBreakageRevenueAction;
use Modules\Hotel\Application\DTOs\BreakageAuditResultDTO;

final class HotelLoyaltyBreakageAuditor
{
    public function __construct(
        protected ?RecognizeLoyaltyBreakageRevenueAction $breakageAction = null
    ) {
        $this->breakageAction = $breakageAction ?? app(RecognizeLoyaltyBreakageRevenueAction::class);
    }

    public function auditAndSweepBreakage(?string $travelPassId = null): BreakageAuditResultDTO
    {
        return $this->breakageAction->execute($travelPassId);
    }
}
