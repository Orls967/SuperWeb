<?php

declare(strict_types=1);

namespace Modules\Port\Application\Actions;

use DomainException;
use Modules\Port\Application\Services\GeneralAverageAdjustmentService;
use Modules\Port\Application\Services\PortFleetLedgerService;
use Modules\Port\Domain\Models\FleetGeneralAverageCase;
use Modules\Port\Domain\Models\FleetVoyage;

/**
 * Action Deklarasi & Alokasi York-Antwerp General Average Sacrifice.
 */
final class DeclareGeneralAverageSacrificeAction
{
    public function __construct(
        private readonly GeneralAverageAdjustmentService $gaService,
        private readonly PortFleetLedgerService $ledgerService
    ) {}

    public function execute(
        int $voyageId,
        string $caseNumber,
        string $incidentDescription,
        string $adjusterFirm,
        float $shipSoundValueUsd,
        float $cargoCommercialValueUsd,
        float $freightAtRiskUsd,
        array $losses
    ): FleetGeneralAverageCase {
        $voyage = FleetVoyage::findOrFail($voyageId);

        if ($shipSoundValueUsd <= 0.0 || $cargoCommercialValueUsd <= 0.0) {
            throw new DomainException('Nilai kontributif kapal dan kargo harus lebih besar dari 0.');
        }

        if (empty($losses)) {
            throw new DomainException('Deklarasi General Average memerlukan minimal satu pengorbanan atau pengeluaran luar biasa.');
        }

        $gaCase = $this->gaService->declareAndAdjustGeneralAverage(
            $voyage->id,
            $caseNumber,
            $incidentDescription,
            $adjusterFirm,
            $shipSoundValueUsd,
            0.0,
            $cargoCommercialValueUsd,
            0.0,
            $freightAtRiskUsd,
            0.0,
            $losses
        );

        // Catat alokasi kontribusi kapal di double-entry fleet ledger
        $this->ledgerService->recordBalancedTransaction(
            $voyage->id,
            $voyage->vessel_id,
            PortFleetLedgerService::ACC_EXPENSE_GENERAL_AVERAGE,
            PortFleetLedgerService::ACC_PAYABLE_DISBURSEMENTS,
            (float) $gaCase->ship_contribution_usd,
            'GENERAL_AVERAGE_DECLARATION',
            $gaCase->id,
            "Alokasi kontribusi General Average untuk {$caseNumber}"
        );

        return $gaCase;
    }
}
