<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot\Tools;

use Modules\Core\Application\Copilot\CopilotToolInterface;
use Modules\Core\Application\Copilot\CopilotToolResult;

/**
 * Deterministic tool for energy smart grid, mining weighbridge, and ESG carbon compliance.
 */
final class EnergyMiningAndSustainabilityTool implements CopilotToolInterface
{
    public function name(): string
    {
        return 'energy_mining_and_sustainability';
    }

    public function description(): string
    {
        return 'Menyajikan metrik Smart Grid PPA, sertifikat REC karbon, rekonsiliasi timbangan tambang, dan emisi Scope 1/2/3.';
    }

    public function moduleScope(): string
    {
        return 'Egy/Mining/Esg';
    }

    public function canHandle(string $prompt): bool
    {
        $normalized = strtolower($prompt);

        return str_contains($normalized, 'energi')
            || str_contains($normalized, 'energy')
            || str_contains($normalized, 'tambang')
            || str_contains($normalized, 'mining')
            || str_contains($normalized, 'esg')
            || str_contains($normalized, 'ppa')
            || str_contains($normalized, 'karbon')
            || str_contains($normalized, 'carbon')
            || str_contains($normalized, 'rec')
            || str_contains($normalized, 'emisi')
            || str_contains($normalized, 'sustainability');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters = []): CopilotToolResult
    {
        $smartGridLoadMw = 450;
        $ppaContractedCapacityMw = 600;
        $recCarbonCreditsRetired = 12000;
        $weighbridgeVerifiedTonnage = 145000;
        $smelterRecoveryRateBps = 9420; // 94.20%
        $scope123EmissionsKg = 842000;
        $sustainabilityComplianceBps = 10000; // 100.00%

        $metrics = [
            'smart_grid_load_mw' => $smartGridLoadMw,
            'ppa_contracted_capacity_mw' => $ppaContractedCapacityMw,
            'rec_carbon_credits_retired' => $recCarbonCreditsRetired,
            'weighbridge_verified_tonnage' => $weighbridgeVerifiedTonnage,
            'smelter_recovery_rate_bps' => $smelterRecoveryRateBps,
            'scope_1_2_3_emissions_kg' => $scope123EmissionsKg,
            'sustainability_compliance_bps' => $sustainabilityComplianceBps,
            'sustainability_status' => 'GRI_STANDARDS_COMPLIANT',
        ];

        $summary = sprintf(
            'Kinerja energi, tambang, dan keberlanjutan konglomerasi: Beban smart grid %d MW dari kapasitas PPA %d MW, '
            .'%d sertifikat REC karbon dipensiunkan, rekonsiliasi timbangan tambang mencatat %s ton konsentrat (recovery rate %d bps), '
            .'dan kepatuhan pelaporan emisi Scope 1/2/3 mencapai %d bps (100,00%%).',
            $smartGridLoadMw,
            $ppaContractedCapacityMw,
            $recCarbonCreditsRetired,
            number_format($weighbridgeVerifiedTonnage, 0, ',', '.'),
            $smelterRecoveryRateBps,
            $sustainabilityComplianceBps
        );

        return CopilotToolResult::create(
            toolName: $this->name(),
            moduleScope: $this->moduleScope(),
            summaryText: $summary,
            metrics: $metrics,
            integrityOk: ($smartGridLoadMw <= $ppaContractedCapacityMw && $sustainabilityComplianceBps === 10000),
        );
    }
}
