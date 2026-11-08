<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Services\FairTradeEscrowSettlementService;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;

class ReleaseFarmerEscrowAction
{
    protected FairTradeEscrowSettlementService $escrowService;

    public function __construct(FairTradeEscrowSettlementService $escrowService)
    {
        $this->escrowService = $escrowService;
    }

    public function execute(int $settlementId, string $approverRole): AquacultureEscrowSettlement
    {
        return $this->escrowService->releaseEscrowPayout($settlementId, $approverRole);
    }
}
