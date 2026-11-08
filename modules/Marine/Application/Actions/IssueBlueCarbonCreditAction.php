<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\BlueCarbonMangroveRestorationService;
use Modules\Marine\Domain\Models\BlueCarbonCreditIssuance;

class IssueBlueCarbonCreditAction
{
    protected BlueCarbonMangroveRestorationService $carbonService;

    public function __construct(BlueCarbonMangroveRestorationService $carbonService)
    {
        $this->carbonService = $carbonService;
    }

    public function execute(int $assessmentId, string $registryName = 'VERRA_REGISTRY'): BlueCarbonCreditIssuance
    {
        return $this->carbonService->issueCarbonCredits($assessmentId, $registryName);
    }
}
