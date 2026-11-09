<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * HospitalInfectionControlSurveillanceService
 *
 * Mengawasi infeksi nosokomial / Hospital-Acquired Infections (HAIs)
 * berdasarkan standar CDC NHSN & Pedoman PPI Kemenkes RI:
 *   1. VAP (Ventilator-Associated Pneumonia).
 *   2. CAUTI (Catheter-Associated Urinary Tract Infection).
 *   3. CLABSI (Central Line-Associated Bloodstream Infection).
 *   4. SSI / IDO (Infection of Surgical Site / Infeksi Daerah Operasi).
 * Menghitung kepadatan insidens (incidence density rate per 1.000 device-days)
 * serta mengklasifikasikan patogen Multi-Drug Resistant Organisms (MDRO: MRSA, ESBL, CRE).
 */
class HospitalInfectionControlSurveillanceService
{
    public const INFECTION_TYPE_VAP = 'VENTILATOR_ASSOCIATED_PNEUMONIA';

    public const INFECTION_TYPE_CAUTI = 'CATHETER_ASSOCIATED_UTI';

    public const INFECTION_TYPE_CLABSI = 'CENTRAL_LINE_BLOODSTREAM_INFECTION';

    public const INFECTION_TYPE_SSI = 'SURGICAL_SITE_INFECTION';

    public const ALERT_LEVEL_GREEN_NORMAL = 'ALERT_GREEN_WITHIN_BENCHMARK';

    public const ALERT_LEVEL_YELLOW_SURVEILLANCE = 'ALERT_YELLOW_ELEVATED_SURVEILLANCE';

    public const ALERT_LEVEL_RED_OUTBREAK = 'ALERT_RED_OUTBREAK_INVESTIGATION';

    /**
     * Ambang batas toleransi internasional (CDC NHSN) per 1.000 hari pemakaian alat:
     *   - VAP: <= 2.5 per 1000 ventilator-days
     *   - CAUTI: <= 2.0 per 1000 catheter-days
     *   - CLABSI: <= 1.5 per 1000 line-days
     */
    private const BENCHMARK_THRESHOLDS = [
        self::INFECTION_TYPE_VAP => ['target' => 2.5, 'outbreak' => 5.0],
        self::INFECTION_TYPE_CAUTI => ['target' => 2.0, 'outbreak' => 4.0],
        self::INFECTION_TYPE_CLABSI => ['target' => 1.5, 'outbreak' => 3.5],
        self::INFECTION_TYPE_SSI => ['target' => 1.8, 'outbreak' => 3.8],
    ];

    /**
     * Hitung tingkat infeksi nosokomial bangsal dan tentukan status outbreak.
     *
     * @param  string  $wardCode  Kode bangsal rawat inap / unit intensif
     * @param  string  $infectionType  Jenis HAIs
     * @param  int  $confirmedInfectionCount  Jumlah kasus infeksi terkonfirmasi biakan kultur
     * @param  int  $totalDeviceDays  Jumlah total hari pemakaian alat pasien di bangsal
     * @param  bool  $isMdroIsolated  Apakah ditemukan bakteri multiresisten (MRSA/CRE/ESBL)
     * @return array{
     *     ward_code: string,
     *     infection_type: string,
     *     incident_rate_per_thousand_days: float,
     *     alert_level: string,
     *     is_outbreak_declared: bool,
     *     requires_cohorting_isolation: bool,
     *     infection_control_actions: array<string>
     * }
     */
    public function calculateInfectionRate(
        string $wardCode,
        string $infectionType,
        int $confirmedInfectionCount,
        int $totalDeviceDays,
        bool $isMdroIsolated
    ): array {
        if (! isset(self::BENCHMARK_THRESHOLDS[$infectionType])) {
            throw new InvalidArgumentException("Tipe infeksi HAIs tidak terdaftar: {$infectionType}");
        }
        if ($totalDeviceDays <= 0) {
            throw new InvalidArgumentException('Total hari pemakaian alat harus lebih besar dari 0');
        }

        $rate = round(($confirmedInfectionCount / $totalDeviceDays) * 1000.0, 2);
        $thresholds = self::BENCHMARK_THRESHOLDS[$infectionType];

        $alert = self::ALERT_LEVEL_GREEN_NORMAL;
        $isOutbreak = false;
        $cohortingRequired = $isMdroIsolated;
        $actions = [];

        if ($rate >= $thresholds['outbreak'] || ($isMdroIsolated && $confirmedInfectionCount >= 2)) {
            $alert = self::ALERT_LEVEL_RED_OUTBREAK;
            $isOutbreak = true;
            $cohortingRequired = true;
            $actions[] = 'DEKLARASI OUTBREAK BANGSAL: Hentikan admisi pasien baru ke ruangan terinfeksi.';
            $actions[] = 'Lakukan isolasi kontak penuh (kohort) dan dekontaminasi total (terminal UV-C cleaning).';
            $actions[] = 'Audit kepatuhan cuci tangan 5-momen (Hand Hygiene) dan sterilisasi alat medis bangsal.';
        } elseif ($rate > $thresholds['target'] || $isMdroIsolated) {
            $alert = self::ALERT_LEVEL_YELLOW_SURVEILLANCE;
            $actions[] = 'Tingkatkan kewaspadaan standar dan surveilans mikrobiologi berkala.';
            $actions[] = 'Kaji ulang indikasi pemakaian alat invasif (pertimbangkan pelepasan kateter/intubasi dini).';
        } else {
            $actions[] = 'Tingkat HAIs berada dalam batas kendali mutu aman.';
        }

        if ($isMdroIsolated) {
            $actions[] = 'Peringatan patogen MDRO: Pasang label Contact Precautions di pintu kamar pasien.';
        }

        return [
            'ward_code' => $wardCode,
            'infection_type' => $infectionType,
            'incident_rate_per_thousand_days' => $rate,
            'alert_level' => $alert,
            'is_outbreak_declared' => $isOutbreak,
            'requires_cohorting_isolation' => $cohortingRequired,
            'infection_control_actions' => $actions,
        ];
    }
}
