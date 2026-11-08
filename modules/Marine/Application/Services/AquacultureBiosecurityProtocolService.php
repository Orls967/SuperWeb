<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\Models\DiseaseHealthScreening;

/**
 * Service protokol biosekuriti & karantina tambak akuakultur intensif (Fase 171).
 */
class AquacultureBiosecurityProtocolService
{
    // Standar konsentrasi klorin disinfeksi air pasok (ppm)
    public const MIN_CHLORINE_DISINFECTION_PPM = 25.0;

    public const MAX_CHLORINE_RESIDUE_AT_STOCKING_PPM = 0.01;

    /**
     * Verifikasi kesiapan biosekuriti air dan petak tambak sebelum tebar benur.
     *
     * @param array{
     *     water_source: string,
     *     chlorine_disinfection_applied_ppm: float,
     *     chlorine_neutralization_days: int,
     *     residual_chlorine_ppm: float,
     *     crab_crab_fence_installed: bool,
     *     bird_scaring_device_active: bool,
     *     reservoir_settling_days: int
     * } $biosecurityAudit
     * @return array{
     *     is_ready_for_stocking: bool,
     *     biosecurity_index: float,
     *     violations: array<string>,
     *     recommendations: array<string>,
     *     clearance_certificate: string
     * }
     */
    public function auditPreStockingBiosecurity(array $biosecurityAudit): array
    {
        $violations = [];
        $recommendations = [];
        $score = 100.0;

        $appliedChlorine = (float) ($biosecurityAudit['chlorine_disinfection_applied_ppm'] ?? 0.0);
        if ($appliedChlorine < self::MIN_CHLORINE_DISINFECTION_PPM) {
            $violations[] = "Dosis disinfeksi klorin ({$appliedChlorine} ppm) di bawah standar minimum 25 ppm.";
            $score -= 30.0;
        }

        $residual = (float) ($biosecurityAudit['residual_chlorine_ppm'] ?? 0.5);
        if ($residual > self::MAX_CHLORINE_RESIDUE_AT_STOCKING_PPM) {
            $violations[] = "Sisa klorin aktif ({$residual} ppm) berbahaya bagi kelangsungan hidup benur.";
            $score -= 40.0;
        }

        if (empty($biosecurityAudit['crab_crab_fence_installed'])) {
            $violations[] = 'Pagar kepiting (crab barrier) belum terpasang di sekeliling pematang tambak.';
            $score -= 15.0;
        }

        if (empty($biosecurityAudit['bird_scaring_device_active'])) {
            $recommendations[] = 'Aktifkan alat penghalau burung pemangsa untuk mencegah transmisi virus WSSV.';
            $score -= 10.0;
        }

        $settlingDays = (int) ($biosecurityAudit['reservoir_settling_days'] ?? 0);
        if ($settlingDays < 3) {
            $recommendations[] = 'Air tandon sebaiknya diendapkan minimal 3 hari sebelum masuk petak pemeliharaan.';
            $score -= 5.0;
        }

        $score = max(0.0, round($score, 1));
        $isReady = empty($violations) && ($score >= 85.0);

        return [
            'is_ready_for_stocking' => $isReady,
            'biosecurity_index' => $score,
            'violations' => $violations,
            'recommendations' => $recommendations,
            'clearance_certificate' => $isReady ? 'BIOSECURITY_LEVEL_3_CLEARED' : 'BIOSECURITY_AUDIT_FAILED',
        ];
    }

    /**
     * Evaluasi risiko sindrom penyakit akut (WSSV, EHP, AHPND) berdasarkan skrining berkala.
     */
    public function assessEpidemiologicalRisk(int $batchId): array
    {
        $batch = AquacultureBatch::findOrFail($batchId);
        $screenings = DiseaseHealthScreening::where('batch_id', $batch->id)->latest('id')->take(5)->get();

        if ($screenings->isEmpty()) {
            return [
                'batch_code' => $batch->batch_code,
                'epidemiological_risk' => 'UNKNOWN_NO_HEALTH_RECORDS',
                'quarantine_action' => 'SCHEDULE_MANDATORY_PCR_TEST',
            ];
        }

        $hasPathogen = false;
        foreach ($screenings as $s) {
            if (! $s->wssv_pcr_negative || ! $s->imnv_pcr_negative || ! $s->ehp_pcr_negative || ! $s->ahpnd_pcr_negative) {
                $hasPathogen = true;
                break;
            }
        }

        if ($hasPathogen) {
            return [
                'batch_code' => $batch->batch_code,
                'epidemiological_risk' => 'CRITICAL_OUTBREAK_SUSPECTED',
                'quarantine_action' => 'ISOLATE_POND_HALT_WATER_DISCHARGE',
            ];
        }

        return [
            'batch_code' => $batch->batch_code,
            'epidemiological_risk' => 'LOW_HEALTHY_POPULATION',
            'quarantine_action' => 'CONTINUE_ROUTINE_BIOSECURITY_MONITORING',
        ];
    }
}
