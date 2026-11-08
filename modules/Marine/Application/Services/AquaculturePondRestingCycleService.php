<?php

declare(strict_types=1);

namespace Modules\Marine\Application\Services;

use DomainException;
use Modules\Marine\Domain\Models\AquacultureBatch;
use Modules\Marine\Domain\StateMachines\AquacultureBatchStateMachine;

/**
 * Service masa bera (fallowing), pengapuran, dan sterilisasi petak tambak pasca panen (Fase 171).
 */
class AquaculturePondRestingCycleService
{
    // Standar masa bera minimum (hari) untuk memutus siklus patogen EHP dan Vibrio
    public const MIN_FALLOWING_DAYS = 14;

    public const MIN_AGRICULTURAL_LIME_DOSAGE_KG_PER_HA = 1000.0; // Dosis kapur CaCO3 / CaO

    protected AquacultureBatchStateMachine $stateMachine;

    public function __construct(AquacultureBatchStateMachine $stateMachine)
    {
        $this->stateMachine = $stateMachine;
    }

    /**
     * Catat mulainya masa bera (fallowing) dan pengeringan dasar tambak.
     */
    public function initiateFallowingPeriod(int $batchId, string $startDate): AquacultureBatch
    {
        $batch = AquacultureBatch::findOrFail($batchId);

        $nextStatus = $this->stateMachine->transition(
            $batch->stage,
            AquacultureBatchStateMachine::STATUS_FALLOWING
        );

        $batch->update([
            'stage' => $nextStatus,
            'notes' => "Masa bera dimulai pada {$startDate}: pengeringan sinar matahari dan pembuangan lumpur organik.",
        ]);

        return $batch;
    }

    /**
     * Verifikasi pengapuran dan sterilisasi tanah dasar tambak.
     *
     * @param array{
     *     lime_type: string,
     *     applied_dosage_kg: float,
     *     pond_area_hectares: float,
     *     soil_ph_before: float,
     *     soil_ph_after: float,
     *     fallowing_duration_days: int
     * } $limingAudit
     * @return array{
     *     is_sterilized_and_ready: bool,
     *     dosage_kg_per_ha: float,
     *     soil_ph_delta: float,
     *     fallowing_compliant: bool,
     *     sanitation_certificate: string
     * }
     */
    public function verifyPondLimingAndSterilization(int $batchId, array $limingAudit): array
    {
        $batch = AquacultureBatch::findOrFail($batchId);

        $areaHa = (float) ($limingAudit['pond_area_hectares'] ?? 0.5);
        $dosageKg = (float) ($limingAudit['applied_dosage_kg'] ?? 0.0);
        $durationDays = (int) ($limingAudit['fallowing_duration_days'] ?? 0);

        if ($areaHa <= 0.0 || $dosageKg <= 0.0) {
            throw new DomainException('Luas tambak dan dosis pengapuran harus lebih besar dari 0.');
        }

        $dosagePerHa = round($dosageKg / $areaHa, 2);
        $fallowingCompliant = ($durationDays >= self::MIN_FALLOWING_DAYS);
        $phBefore = (float) ($limingAudit['soil_ph_before'] ?? 5.5);
        $phAfter = (float) ($limingAudit['soil_ph_after'] ?? 7.5);
        $phDelta = round($phAfter - $phBefore, 2);

        $isReady = $fallowingCompliant && ($dosagePerHa >= self::MIN_AGRICULTURAL_LIME_DOSAGE_KG_PER_HA) && ($phAfter >= 7.0);

        if ($isReady) {
            $nextStatus = $this->stateMachine->transition(
                $batch->stage,
                AquacultureBatchStateMachine::STATUS_POND_STERILIZED
            );
            $batch->update([
                'stage' => $nextStatus,
                'notes' => 'Tambak telah disterilisasi dan siap tebar benur siklus berikutnya.',
            ]);
        }

        return [
            'is_sterilized_and_ready' => $isReady,
            'dosage_kg_per_ha' => $dosagePerHa,
            'soil_ph_delta' => $phDelta,
            'fallowing_compliant' => $fallowingCompliant,
            'sanitation_certificate' => $isReady ? 'POND_SANITATION_PASSED' : 'ADDITIONAL_SUN_DRYING_REQUIRED',
        ];
    }
}
