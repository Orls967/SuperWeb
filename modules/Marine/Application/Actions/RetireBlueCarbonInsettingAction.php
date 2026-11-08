<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\SilvofisheryCarbonOffsetLedgerService;

class RetireBlueCarbonInsettingAction
{
    protected SilvofisheryCarbonOffsetLedgerService $offsetService;

    public function __construct(SilvofisheryCarbonOffsetLedgerService $offsetService)
    {
        $this->offsetService = $offsetService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(int $exportLotId, int $creditIssuanceId, float $lotEmissionsTonnesCo2e): array
    {
        return $this->offsetService->retireCreditsForLotInsetting($exportLotId, $creditIssuanceId, $lotEmissionsTonnesCo2e);
    }
}

