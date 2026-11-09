<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * DialysisWaterPurificationMonitorService
 *
 * Mengawasi integritas mikrobiologis dan kimiawi air murni hemodialisis (Reverse Osmosis / RO)
 * berdasarkan standar AAMI / ISO 23500 & Pedoman Konsensus Pernefri.
 * Memeriksa parameter konduktivitas, endotoksin bakteri (LAL test), dan total count mikroba.
 */
class DialysisWaterPurificationMonitorService
{
    // Batas mutu AAMI / ISO 23500
    public const MAX_CONDUCTIVITY_MICROS_PER_CM = 20.0;

    public const MAX_ACTION_CONDUCTIVITY = 10.0;

    public const MAX_BACTERIA_CFU_PER_ML = 100.0;

    public const ACTION_BACTERIA_CFU_PER_ML = 50.0;

    public const MAX_ENDOTOXIN_EU_PER_ML = 0.25;

    public const ACTION_ENDOTOXIN_EU_PER_ML = 0.125;

    public const STATUS_OPTIMAL = 'WATER_QUALITY_OPTIMAL';

    public const STATUS_ACTION_REQUIRED = 'ACTION_LEVEL_WARNING';

    public const STATUS_HALT_DIALYSIS = 'CRITICAL_HALT_DIALYSIS';

    /**
     * Evaluasi parameter kualitas air dialisis.
     *
     * @param  string  $roLoopIdentifier  ID loop distribusi pipa RO
     * @param  float  $conductivityMicroSiemens  Konduktivitas elektrik (µS/cm)
     * @param  float  $bacteriaColonyCfuPerMl  Hitung koloni bakteri (CFU/mL)
     * @param  float  $endotoxinEuPerMl  Kadar endotoksin (EU/mL)
     * @param  float  $chlorinePpm  Kadar klorin bebas/total (ppm)
     * @return array{
     *     loop_id: string,
     *     status: string,
     *     is_safe_for_patient_treatment: bool,
     *     breached_parameters: array<string>,
     *     remediation_actions: array<string>,
     *     disinfection_protocol_required: bool
     * }
     */
    public function evaluateDialysisWaterQuality(
        string $roLoopIdentifier,
        float $conductivityMicroSiemens,
        float $bacteriaColonyCfuPerMl,
        float $endotoxinEuPerMl,
        float $chlorinePpm
    ): array {
        if (empty($roLoopIdentifier)) {
            throw new InvalidArgumentException('RO Loop identifier tidak boleh kosong');
        }

        $breached = [];
        $actions = [];
        $isSafe = true;
        $status = self::STATUS_OPTIMAL;
        $disinfectionRequired = false;

        // Klorin adalah racun fatal bagi pasien hemodialisis (bisa lisis eritrosit)
        if ($chlorinePpm > 0.10) {
            $breached[] = sprintf('Total Chlorine %.3f ppm melampaui batas kritis 0.10 ppm', $chlorinePpm);
            $isSafe = false;
            $status = self::STATUS_HALT_DIALYSIS;
            $actions[] = 'HENTIKAN SEGERA sesi hemodialisis. Pasien dalam risiko hemolisis akut.';
            $actions[] = 'Lakukan regenerasi atau penggantian karbon aktif (carbon bed adsorption).';
        }

        if ($conductivityMicroSiemens > self::MAX_CONDUCTIVITY_MICROS_PER_CM) {
            $breached[] = sprintf('Konduktivitas %.2f µS/cm melampaui batas maksimum 20 µS/cm', $conductivityMicroSiemens);
            $isSafe = false;
            $status = self::STATUS_HALT_DIALYSIS;
            $actions[] = 'Periksa membran RO utama dan deionisator (DI bed). Ada kebocoran ion terlarut.';
        } elseif ($conductivityMicroSiemens > self::MAX_ACTION_CONDUCTIVITY) {
            $breached[] = sprintf('Konduktivitas %.2f µS/cm mencapai action level (10-20 µS/cm)', $conductivityMicroSiemens);
            if ($status !== self::STATUS_HALT_DIALYSIS) {
                $status = self::STATUS_ACTION_REQUIRED;
            }
            $actions[] = 'Jadwalkan flushing membran RO dan cek status garam softener.';
        }

        if ($bacteriaColonyCfuPerMl >= self::MAX_BACTERIA_CFU_PER_ML) {
            $breached[] = sprintf('Kultur bakteri %.1f CFU/mL melampaui ambang bahaya 100 CFU/mL', $bacteriaColonyCfuPerMl);
            $isSafe = false;
            $status = self::STATUS_HALT_DIALYSIS;
            $disinfectionRequired = true;
            $actions[] = 'Sterilisasi kimiawi menyeluruh sistem pipa loop menggunakan asam perasetat / ozonisasi.';
        } elseif ($bacteriaColonyCfuPerMl >= self::ACTION_BACTERIA_CFU_PER_ML) {
            $breached[] = sprintf('Kultur bakteri %.1f CFU/mL memasuki action level (50-100 CFU/mL)', $bacteriaColonyCfuPerMl);
            if ($status !== self::STATUS_HALT_DIALYSIS) {
                $status = self::STATUS_ACTION_REQUIRED;
            }
            $disinfectionRequired = true;
            $actions[] = 'Lakukan desinfeksi termal air panas (heat disinfection) pada loop pipa distribusi.';
        }

        if ($endotoxinEuPerMl >= self::MAX_ENDOTOXIN_EU_PER_ML) {
            $breached[] = sprintf('Kadar endotoksin %.3f EU/mL melampaui ambang batas 0.25 EU/mL', $endotoxinEuPerMl);
            $isSafe = false;
            $status = self::STATUS_HALT_DIALYSIS;
            $actions[] = 'Ganti ultrafilter membran endotoksin (polysulfone filter stage) pada outlet RO.';
        } elseif ($endotoxinEuPerMl >= self::ACTION_ENDOTOXIN_EU_PER_ML) {
            $breached[] = sprintf('Kadar endotoksin %.3f EU/mL mendekati batas waspada 0.125 EU/mL', $endotoxinEuPerMl);
            if ($status !== self::STATUS_HALT_DIALYSIS) {
                $status = self::STATUS_ACTION_REQUIRED;
            }
            $actions[] = 'Lakukan re-sampling endotoksin dalam 48 jam dan audit sampling port.';
        }

        return [
            'loop_id' => $roLoopIdentifier,
            'status' => $status,
            'is_safe_for_patient_treatment' => $isSafe,
            'breached_parameters' => $breached,
            'remediation_actions' => $actions,
            'disinfection_protocol_required' => $disinfectionRequired,
        ];
    }
}
