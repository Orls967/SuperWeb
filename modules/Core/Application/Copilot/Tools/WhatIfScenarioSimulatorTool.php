<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot\Tools;

use Modules\Core\Application\Copilot\CopilotToolInterface;
use Modules\Core\Application\Copilot\CopilotToolResult;

/**
 * Deterministic tool for What-If executive scenario simulations using integer minor-unit arithmetic.
 */
final class WhatIfScenarioSimulatorTool implements CopilotToolInterface
{
    public function name(): string
    {
        return 'what_if_scenario_simulator';
    }

    public function description(): string
    {
        return 'Menjalankan simulasi deterministik What-If (lonjakan beban 20%, depresiasi kurs 500 bps, lonjakan permintaan 3x) menggunakan aritmetika integer minor-unit intdiv.';
    }

    public function moduleScope(): string
    {
        return 'Core/Simulation/WhatIf';
    }

    public function canHandle(string $prompt): bool
    {
        $normalized = strtolower($prompt);

        return str_contains($normalized, 'simulasi')
            || str_contains($normalized, 'scenario')
            || str_contains($normalized, 'skenario')
            || str_contains($normalized, 'what-if')
            || str_contains($normalized, 'what if')
            || str_contains($normalized, 'lonjakan')
            || str_contains($normalized, 'surge')
            || str_contains($normalized, 'depresiasi')
            || str_contains($normalized, 'beban')
            || str_contains($normalized, 'stress');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters = []): CopilotToolResult
    {
        $scenarioRaw = (string) ($parameters['scenario'] ?? $parameters['prompt'] ?? 'load_surge_20');
        $normalized = strtolower($scenarioRaw);

        // Deterministic integer baseline figures (minor-units: IDR cents, 1 IDR = 100 minor-units)
        $baselineLiquidityMinorUnits = 64285000000000; // Rp 642.850.000.000
        $baselineMonthlyRevenueMinorUnits = 18500000000000; // Rp 185.000.000.000

        if (str_contains($normalized, 'kurs') || str_contains($normalized, 'fx') || str_contains($normalized, '500')) {
            // Scenario 1: FX Depreciation 500 bps (5.00%)
            $scenarioName = 'Depresiasi Kurs Valas 500 bps (USD/IDR)';
            $surgeBps = 500;
            // Cost surge: baseline monthly revenue * 500 / 10000
            $ebitdaImpactMinorUnits = -intdiv($baselineMonthlyRevenueMinorUnits * $surgeBps, 10000); // -Rp 9.250.000.000
            $liquidityHeadroomMinorUnits = $baselineLiquidityMinorUnits + $ebitdaImpactMinorUnits;
            $recommendedReserveBufferBps = 1500; // 15.00%
            $mitigations = [
                'Eksekusi auto-sweep otomatis perbendaharaan ke portofolio forward contract USD/IDR dengan kupon lindung nilai terproteksi.',
                'Terapkan penyesuaian klausul landed-cost bea impor pada modul International & TradeFinance untuk memitigasi selisih kurs.',
                'Prioritaskan pembayaran utang valas jangka pendek dari cadangan devisa anak usaha ekspor tambang (Mining SBU).',
            ];
        } elseif (str_contains($normalized, '3x') || str_contains($normalized, 'tiga kali') || str_contains($normalized, 'demand')) {
            // Scenario 2: Demand Surge 3x (30000 bps)
            $scenarioName = 'Lonjakan Permintaan Pasar 3x (Demand Shock)';
            $surgeBps = 30000;
            $ebitdaImpactMinorUnits = intdiv($baselineMonthlyRevenueMinorUnits * 14000, 10000); // +Rp 259.000.000.000
            $liquidityHeadroomMinorUnits = $baselineLiquidityMinorUnits + intdiv($ebitdaImpactMinorUnits, 2);
            $recommendedReserveBufferBps = 3000; // 30.00%
            $mitigations = [
                'Aktifkan alokasi buffer stock dinamis di seluruh pergudangan WMS dan percepat putaway kurir logistik 3PL.',
                'Operasikan shift 3 pada seluruh lini perakitan manufaktur dengan persetujuan overtime otomatis modul HCM.',
                'Gunakan dynamic pricing waterfall untuk meredam lonjakan tak tertampung dan menjaga margin laba bersih.',
            ];
        } else {
            // Scenario 3 (Default): Network & Operational Load Surge 20% (2000 bps)
            $scenarioName = 'Lonjakan Beban Operasional & Jaringan 20%';
            $surgeBps = 2000;
            // Additional operating cost/capex requirement
            $ebitdaImpactMinorUnits = -intdiv($baselineMonthlyRevenueMinorUnits * 850, 10000); // -Rp 1.572.500.000 (Opex surge)
            $liquidityHeadroomMinorUnits = $baselineLiquidityMinorUnits + $ebitdaImpactMinorUnits;
            $recommendedReserveBufferBps = 2200; // 22.00%
            $mitigations = [
                'Aktifkan auto-scaling horizontal armada server dan alokasikan penambahan daya PPA microgrid sebesar 20 MW.',
                'Naikkan safety stock pergudangan WMS sebesar 15% pada 50 SKU komponen berkategori Fast Moving.',
                'Distribusikan beban rute logistik ke armada cadangan otonom untuk menjaga SLA OTIF di atas 98,00%.',
            ];
        }

        $metrics = [
            'scenario_identified' => $scenarioName,
            'load_surge_bps' => $surgeBps,
            'projected_ebitda_impact_minor_units' => $ebitdaImpactMinorUnits,
            'liquidity_buffer_headroom_minor_units' => $liquidityHeadroomMinorUnits,
            'recommended_reserve_buffer_bps' => $recommendedReserveBufferBps,
            'mitigation_actions_count' => count($mitigations),
            'simulation_status' => 'DETERMINISTIC_RESILIENT',
        ];

        $summary = sprintf(
            'Hasil Simulasi What-If [%s]: Dampak terhadap EBITDA diproyeksikan sebesar Rp %s dengan sisa likuiditas kas Rp %s. '
            .'Buffer cadangan yang disarankan adalah %d bps (%s%%). Dihasilkan %d langkah mitigasi eksekutif terstruktur.',
            $scenarioName,
            number_format(intdiv($ebitdaImpactMinorUnits, 100), 0, ',', '.'),
            number_format(intdiv($liquidityHeadroomMinorUnits, 100), 0, ',', '.'),
            $recommendedReserveBufferBps,
            number_format($recommendedReserveBufferBps / 100, 2, ',', '.'),
            count($mitigations)
        );

        return CopilotToolResult::create(
            toolName: $this->name(),
            moduleScope: $this->moduleScope(),
            summaryText: $summary,
            metrics: $metrics,
            integrityOk: ($liquidityHeadroomMinorUnits > 0),
        );
    }
}
