<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Actions;

use Modules\Marine\Application\Sagas\AssayDisputeArbitrationSaga;
use Modules\Marine\Domain\Models\AquacultureEscrowSettlement;
use Modules\Marine\Domain\Models\AquacultureExportLot;

class ArbitrateAssayDisputeAction
{
    protected AssayDisputeArbitrationSaga $saga;

    public function __construct(AssayDisputeArbitrationSaga $saga)
    {
        $this->saga = $saga;
    }

    /**
     * @param  array<string, mixed>  $arbitrationLabResults
     * @return array<string, mixed>
     */
    public function execute(
        string $disputeReference,
        AquacultureExportLot $exportLot,
        AquacultureEscrowSettlement $escrowSettlement,
        string $destinationMarket,
        array $arbitrationLabResults
    ): array {
        return $this->saga->arbitrate(
            $disputeReference,
            $exportLot,
            $escrowSettlement,
            $destinationMarket,
            $arbitrationLabResults
        );
    }
}
