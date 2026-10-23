<?php

declare(strict_types=1);

namespace Modules\Esg\Application\Services;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Esg\Domain\Models\EsgCarbonLog;

class ConsolidatedTwelveLinesEsgService
{
    public function __construct(
        protected Ledger $ledger
    ) {}

    public function recordEmission(
        string $domainCode,
        string $scopeLevel,
        string $activityName,
        int $volume,
        string $unitCode,
        int $factorBps,
        string $period
    ): EsgCarbonLog {
        $co2Kg = (int) round(($volume * $factorBps) / 10000);

        return EsgCarbonLog::query()->create([
            'domain_code' => strtoupper($domainCode),
            'scope_level' => strtoupper($scopeLevel),
            'activity_name' => $activityName,
            'activity_volume' => $volume,
            'unit_code' => $unitCode,
            'emission_factor_bps' => $factorBps,
            'co2_emitted_kg' => $co2Kg,
            'reporting_period' => $period,
        ]);
    }

    /**
     * @return array{reporting_period: string, scope_1_kg: int, scope_2_kg: int, scope_3_kg: int, net_co2_kg: int, compliance_status: string}
     */
    public function aggregateConsolidatedReport(string $period): array
    {
        $scope1 = (int) EsgCarbonLog::query()->where('reporting_period', $period)->where('scope_level', 'SCOPE_1')->sum('co2_emitted_kg');
        $scope2 = (int) EsgCarbonLog::query()->where('reporting_period', $period)->where('scope_level', 'SCOPE_2')->sum('co2_emitted_kg');
        $scope3 = (int) EsgCarbonLog::query()->where('reporting_period', $period)->where('scope_level', 'SCOPE_3')->sum('co2_emitted_kg');
        $netCo2Kg = $scope1 + $scope2 + $scope3;

        return [
            'reporting_period' => $period,
            'scope_1_kg' => $scope1,
            'scope_2_kg' => $scope2,
            'scope_3_kg' => $scope3,
            'net_co2_kg' => $netCo2Kg,
            'compliance_status' => $netCo2Kg <= 50000000 ? 'COMPLIANT_GRI' : 'OFFSET_REQUIRED',
        ];
    }

    /**
     * @return array{retired_tonnes: int, total_cost_idr: int, status: string}
     */
    public function retireCarbonCredits(
        int $creditsToRetireTonnes,
        int $costPerTonneIdr,
        string $retirementCertRef
    ): array {
        $totalCost = $creditsToRetireTonnes * $costPerTonneIdr;
        $idempotencyKey = 'carbon_retire:'.hash('sha256', $retirementCertRef);

        $this->ledger->post(new PostingDTO(
            type: 'settlement',
            description: "Retired {$creditsToRetireTonnes} tonnes CO2e certificate {$retirementCertRef}",
            idempotencyKey: $idempotencyKey,
            entries: [
                PostingEntryDTO::forCode('esg:carbon_offset_expense:IDR', 'IDR', -$totalCost),
                PostingEntryDTO::forCode('esg:carbon_credit_inventory:IDR', 'IDR', $totalCost),
            ],
            referenceType: 'esg_carbon_retirements',
            referenceId: (string) hash('crc32b', $retirementCertRef),
        ));

        return [
            'retired_tonnes' => $creditsToRetireTonnes,
            'total_cost_idr' => $totalCost,
            'status' => 'RETIRED_VERIFIED',
        ];
    }
}
