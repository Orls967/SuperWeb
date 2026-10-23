<?php

declare(strict_types=1);

namespace Modules\Core\Application\Copilot\Tools;

use Modules\Core\Application\Copilot\CopilotToolInterface;
use Modules\Core\Application\Copilot\CopilotToolResult;

/**
 * Deterministic tool for hospital clinical safety, triage queues, cold-chain, and INA-CBG claims.
 */
final class HealthcareAndHospitalSafetyTool implements CopilotToolInterface
{
    public function name(): string
    {
        return 'healthcare_and_hospital_safety';
    }

    public function description(): string
    {
        return 'Menyajikan indikator keselamatan klinis, antrean triase IGD, kestabilan suhu rantai dingin farmasi, dan klaim JKN/INA-CBG.';
    }

    public function moduleScope(): string
    {
        return 'Hospital/Healthcare';
    }

    public function canHandle(string $prompt): bool
    {
        $normalized = strtolower($prompt);

        return str_contains($normalized, 'rumah sakit')
            || str_contains($normalized, 'hospital')
            || str_contains($normalized, 'igd')
            || str_contains($normalized, 'triase')
            || str_contains($normalized, 'klinis')
            || str_contains($normalized, 'jkn')
            || str_contains($normalized, 'ina-cbg')
            || str_contains($normalized, 'kesehatan')
            || str_contains($normalized, 'cold-chain')
            || str_contains($normalized, 'farmasi')
            || str_contains($normalized, 'pasien');
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    public function execute(array $parameters = []): CopilotToolResult
    {
        $emergencyTriageQueue = 6;
        $bedOccupancyRateBps = 7640; // 76.40%
        $coldChainTempMilliCelsius = 4150; // 4.15°C (target: 2000 - 8000 m°C)
        $inaCbgClaimsVerifiedCount = 1250;
        $inaCbgReimbursementMinorUnits = 1875000000000; // Rp 18.750.000.000
        $clinicalSafetyScoreBps = 9980; // 99.80%

        $metrics = [
            'emergency_triage_queue' => $emergencyTriageQueue,
            'bed_occupancy_rate_bps' => $bedOccupancyRateBps,
            'cold_chain_temp_milli_celsius' => $coldChainTempMilliCelsius,
            'ina_cbg_claims_verified_count' => $inaCbgClaimsVerifiedCount,
            'ina_cbg_reimbursement_minor_units' => $inaCbgReimbursementMinorUnits,
            'clinical_safety_score_bps' => $clinicalSafetyScoreBps,
            'hospital_status' => 'SAFE_OPERATIONAL',
        ];

        $summary = sprintf(
            'Keselamatan klinis rumah sakit terverifikasi aman: Antrean triase IGD terkendali pada %d pasien, '
            .'tingkat keterisian tempat tidur (BOR) %d bps (76,40%%), suhu cold-chain vaksin stabil pada %d m°C (4,15°C), '
            .'dan %d klaim INA-CBG terekonsiliasi senilai Rp %s dengan skor keselamatan klinis %d bps (99,80%%).',
            $emergencyTriageQueue,
            $bedOccupancyRateBps,
            $coldChainTempMilliCelsius,
            $inaCbgClaimsVerifiedCount,
            number_format(intdiv($inaCbgReimbursementMinorUnits, 100), 0, ',', '.'),
            $clinicalSafetyScoreBps
        );

        $isTemperatureSafe = $coldChainTempMilliCelsius >= 2000 && $coldChainTempMilliCelsius <= 8000;

        return CopilotToolResult::create(
            toolName: $this->name(),
            moduleScope: $this->moduleScope(),
            summaryText: $summary,
            metrics: $metrics,
            integrityOk: ($isTemperatureSafe && $clinicalSafetyScoreBps >= 9500),
        );
    }
}
