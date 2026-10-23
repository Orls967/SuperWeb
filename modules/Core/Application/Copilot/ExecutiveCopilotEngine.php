<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot;

use Modules\Core\Application\Copilot\Tools\ArchitectureAndAuditIntegrityTool;
use Modules\Core\Application\Copilot\Tools\EnergyMiningAndSustainabilityTool;
use Modules\Core\Application\Copilot\Tools\HealthcareAndHospitalSafetyTool;
use Modules\Core\Application\Copilot\Tools\LedgerAndTreasuryHealthTool;
use Modules\Core\Application\Copilot\Tools\SupplyChainAndManufacturingPulseTool;
use Modules\Core\Application\Copilot\Tools\WhatIfScenarioSimulatorTool;

/**
 * Deterministic Executive Copilot Engine cross-cutting 46 modules.
 */
final class ExecutiveCopilotEngine
{
    /** @var list<CopilotToolInterface> */
    private array $tools;

    /**
     * @param  iterable<CopilotToolInterface>|null  $customTools
     */
    public function __construct(?iterable $customTools = null)
    {
        if ($customTools !== null) {
            $this->tools = is_array($customTools) ? array_values($customTools) : iterator_to_array($customTools, false);
        } else {
            $this->tools = [
                new LedgerAndTreasuryHealthTool,
                new ArchitectureAndAuditIntegrityTool,
                new SupplyChainAndManufacturingPulseTool,
                new HealthcareAndHospitalSafetyTool,
                new EnergyMiningAndSustainabilityTool,
                new WhatIfScenarioSimulatorTool,
            ];
        }
    }

    /**
     * @return list<CopilotToolInterface>
     */
    public function registeredTools(): array
    {
        return $this->tools;
    }

    /**
     * Deterministically answer executive inquiries and run tool-calling pipeline.
     *
     * @param  array<string, mixed>  $options
     */
    public function ask(string $prompt, array $options = []): ExecutiveCopilotResponse
    {
        $selectedTools = [];

        // Check if a specific scenario option was provided
        $scenarioOption = $options['scenario'] ?? null;

        foreach ($this->tools as $tool) {
            $shouldInclude = $tool->canHandle($prompt);

            if ($scenarioOption !== null && $tool instanceof WhatIfScenarioSimulatorTool) {
                $shouldInclude = true;
            }

            if ($shouldInclude) {
                $selectedTools[] = $tool;
            }
        }

        // If no tool explicitly matched, fallback to architecture and ledger health
        if (empty($selectedTools)) {
            foreach ($this->tools as $tool) {
                if ($tool instanceof ArchitectureAndAuditIntegrityTool || $tool instanceof LedgerAndTreasuryHealthTool) {
                    $selectedTools[] = $tool;
                }
            }
        }

        $toolResults = [];
        $kpiTable = [];
        $summaries = [];
        $mitigations = [];
        $allIntact = true;
        $proofHashes = [];

        foreach ($selectedTools as $tool) {
            $params = array_merge($options, [
                'prompt' => $prompt,
                'scenario' => $scenarioOption ?? $prompt,
            ]);

            $result = $tool->execute($params);
            $toolResults[] = $result;
            $summaries[] = "[{$result->moduleScope}] {$result->summaryText}";
            $proofHashes[] = $result->executionProofHash;

            if (! $result->integrityVerified) {
                $allIntact = false;
            }

            foreach ($result->metrics as $metricKey => $metricVal) {
                $kpiTable[$metricKey] = $metricVal;
            }

            // Extract mitigations if WhatIf tool
            if ($tool instanceof WhatIfScenarioSimulatorTool) {
                $scenarioName = (string) ($result->metrics['scenario_identified'] ?? 'Skenario Teridentifikasi');
                $mitigations[] = "Skenario [{$scenarioName}]: Pertahankan likuiditas kas di atas Rp "
                    .number_format(intdiv((int) ($result->metrics['liquidity_buffer_headroom_minor_units'] ?? 0), 100), 0, ',', '.')
                    ." dan alokasikan buffer cadangan {$result->metrics['recommended_reserve_buffer_bps']} bps.";
                $mitigations[] = 'Terapkan dynamic pricing waterfall dan prioritaskan pemenuhan pesanan dengan margin kontribusi tertinggi.';
                $mitigations[] = 'Sinkronkan data kapasitas real-time ke modul ControlTower dan WMS untuk menghindari bottleneck pergudangan.';
            }
        }

        $masterSummary = implode(' ', $summaries);

        // Compute deterministic Master HMAC-SHA256 overall proof hash
        $secret = (string) config('app.key', 'autoserve-executive-copilot-deterministic-secret');
        $canonicalMasterPayload = json_encode([
            'prompt' => $prompt,
            'tool_hashes' => $proofHashes,
            'kpis' => $kpiTable,
            'intact' => $allIntact,
        ], JSON_THROW_ON_ERROR);

        $overallProofHash = hash_hmac('sha256', $canonicalMasterPayload, $secret);

        return new ExecutiveCopilotResponse(
            prompt: $prompt,
            summary: $masterSummary,
            toolResults: $toolResults,
            kpiTable: $kpiTable,
            mitigationRecommendations: $mitigations,
            overallProofHash: $overallProofHash,
            integrityOk: $allIntact,
        );
    }
}
