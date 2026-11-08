<?php

declare(strict_types=1);

namespace Modules\Port\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Port\Domain\Models\FleetGeneralAverageCase;
use Modules\Port\Domain\Models\FleetVoyage;
use Modules\Shared\Domain\Algorithms\Port\GeneralAverageContributionDistributor;

/**
 * Service Penyesuaian Kerugian Bersama (General Average Adjustment Service).
 * Standar: York-Antwerp Rules 2016 (CMI).
 */
final class GeneralAverageAdjustmentService
{
    public function __construct(
        private readonly GeneralAverageContributionDistributor $distributor = new GeneralAverageContributionDistributor()
    ) {}

    /**
     * Deklarasikan General Average dan hitung penyesuaian kontribusi awal (Adjustment Statement).
     *
     * @param int $voyageId
     * @param string $caseNumber
     * @param string $incidentDesc
     * @param string $adjusterFirm
     * @param float $shipSoundValueUsd
     * @param float $shipParticularDamageUsd
     * @param float $cargoCommercialValueUsd
     * @param float $cargoParticularDamageUsd
     * @param float $freightAtRiskUsd
     * @param float $freightExpensesSavedUsd
     * @param array<int, array{interest: string, description: string, amount_usd: float, category: string}> $losses
     */
    public function declareAndAdjustGeneralAverage(
        int $voyageId,
        string $caseNumber,
        string $incidentDesc,
        string $adjusterFirm,
        float $shipSoundValueUsd,
        float $shipParticularDamageUsd,
        float $cargoCommercialValueUsd,
        float $cargoParticularDamageUsd,
        float $freightAtRiskUsd,
        float $freightExpensesSavedUsd,
        array $losses
    ): FleetGeneralAverageCase {
        $voyage = FleetVoyage::findOrFail($voyageId);

        $adjustment = $this->distributor->calculateAdjustment(
            $shipSoundValueUsd,
            $shipParticularDamageUsd,
            $cargoCommercialValueUsd,
            $cargoParticularDamageUsd,
            $freightAtRiskUsd,
            $freightExpensesSavedUsd,
            $losses
        );

        $apportionment = $adjustment['apportionment'];

        return FleetGeneralAverageCase::create([
            'voyage_id' => $voyage->id,
            'case_number' => $caseNumber,
            'incident_description' => $incidentDesc,
            'declared_at' => CarbonImmutable::now()->toDateTimeString(),
            'average_adjuster_firm' => $adjusterFirm,
            'ship_contributory_value_usd' => $adjustment['ship_contributory_value'],
            'cargo_contributory_value_usd' => $adjustment['cargo_contributory_value'],
            'freight_contributory_value_usd' => $adjustment['freight_contributory_value'],
            'total_contributory_value_usd' => $adjustment['total_contributory_value'],
            'total_sacrifices_usd' => $adjustment['total_sacrifices'],
            'total_expenditures_usd' => $adjustment['total_expenditures'],
            'general_average_loss_usd' => $adjustment['total_general_average_loss'],
            'contribution_rate_percentage' => $adjustment['contribution_rate_percentage'],
            'ship_contribution_usd' => $apportionment['ship']['gross_contribution'],
            'cargo_contribution_usd' => $apportionment['cargo']['gross_contribution'],
            'freight_contribution_usd' => $apportionment['freight']['gross_contribution'],
            'adjustment_status' => 'DECLARED',
        ]);
    }
}

