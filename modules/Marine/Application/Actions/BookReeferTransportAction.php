<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use App\Models\User;
use Modules\Marine\Application\Services\AquacultureVesselColdChainLogisticsService;

class BookReeferTransportAction
{
    protected AquacultureVesselColdChainLogisticsService $logisticsService;

    public function __construct(AquacultureVesselColdChainLogisticsService $logisticsService)
    {
        $this->logisticsService = $logisticsService;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(
        User $shipper,
        int $exportLotId,
        string $originPortCode,
        string $destinationCity,
        int $freightFeeMinorCents
    ): array {
        return $this->logisticsService->bookReeferLogisticsTransfer(
            $shipper,
            $exportLotId,
            $originPortCode,
            $destinationCity,
            $freightFeeMinorCents
        );
    }
}

