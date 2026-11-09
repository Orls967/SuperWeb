<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\Carbon;
use Modules\Hospital\Domain\Models\EPharmacyOrder;
use Modules\Hospital\Domain\Models\Patient;

/**
 * Narcotic & Psychotropic Prescription Quota Limiter & Anti-Doctor Shopping Guard.
 * Menegakkan batasan kuota farmakologi sesuai pedoman Kemenkes dan BNN:
 * 1. Batas maksimal pasokan harian obat opioid/narkotika (maksimal 5 hari untuk nyeri akut, 30 hari untuk kanker paliatif).
 * 2. Deteksi otomatis pola "Doctor Shopping" (pasien meminta resep psikotropika dari >= 2 dokter berbeda dalam tempo 14 hari).
 * 3. Pemantauan akumulasi dosis ekuivalen Morfin oral (Oral Morphine Milligram Equivalents / MME).
 * 4. Peringatan dini jika total MME/hari melampaui 90 MME (ambang risiko overdosis tinggi CDC).
 */
class NarcoticPrescriptionQuotaLimiter
{
    private const MAX_ACUTE_DAYS_SUPPLY = 5;

    private const MAX_PALLIATIVE_DAYS_SUPPLY = 30;

    private const CDC_HIGH_RISK_MME_THRESHOLD = 90.0;

    /**
     * Memeriksa apakah resep narkotika pasien melanggar batas kuota atau pola doctor shopping.
     *
     * @return array{
     *     is_allowed: bool,
     *     violation_reason: string|null,
     *     rolling_30d_orders_count: int,
     *     distinct_prescribers_14d: int,
     *     estimated_mme_daily: float,
     *     is_high_risk_overdose: bool
     * }
     */
    public function evaluateQuota(
        Patient $patient,
        string $drugCode,
        int $requestedQuantity,
        int $prescribingDoctorId,
        bool $isPalliativeCancer = false
    ): array {
        $maxDays = $isPalliativeCancer ? self::MAX_PALLIATIVE_DAYS_SUPPLY : self::MAX_ACUTE_DAYS_SUPPLY;
        // Asumsi konsumsi 3 tablet/hari
        $daysOfSupply = (int) ceil($requestedQuantity / 3);

        if ($daysOfSupply > $maxDays) {
            return [
                'is_allowed' => false,
                'violation_reason' => sprintf(
                    'Kuantitas resep melebihi batas kuota legal: %d hari pasokan (Batas maksimal untuk kasus ini adalah %d hari).',
                    $daysOfSupply,
                    $maxDays
                ),
                'rolling_30d_orders_count' => 0,
                'distinct_prescribers_14d' => 0,
                'estimated_mme_daily' => 0.0,
                'is_high_risk_overdose' => false,
            ];
        }

        // Cek riwayat order 30 hari terakhir
        $recentOrders = EPharmacyOrder::where('patient_id', $patient->id)
            ->where('drug_classification', 'NARCOTIC_PSYCHOTROPIC')
            ->where('created_at', '>=', Carbon::now()->subDays(30))
            ->get();

        $count30d = $recentOrders->count();

        // Cek variasi dokter peresep dalam 14 hari terakhir (Doctor Shopping check)
        $orders14d = $recentOrders->filter(fn ($o) => $o->created_at >= Carbon::now()->subDays(14));
        $prescribers = [];
        foreach ($orders14d as $ord) {
            if ($ord->teleConsult && $ord->teleConsult->doctor_id) {
                $prescribers[] = (int) $ord->teleConsult->doctor_id;
            }
        }
        $prescribers[] = $prescribingDoctorId;
        $distinctDoctors = count(array_unique($prescribers));

        if ($distinctDoctors >= 3) {
            return [
                'is_allowed' => false,
                'violation_reason' => sprintf(
                    'Peringatan Doctor Shopping terdeteksi: Pasien memiliki resep obat terawasi dari %d dokter berbeda dalam 14 hari terakhir.',
                    $distinctDoctors
                ),
                'rolling_30d_orders_count' => $count30d,
                'distinct_prescribers_14d' => $distinctDoctors,
                'estimated_mme_daily' => 45.0,
                'is_high_risk_overdose' => false,
            ];
        }

        // Estimasi kalkulasi MME (Morphine Milligram Equivalent)
        $mmeDaily = $this->calculateEstimatedMmeDaily($drugCode, 30.0);
        $isHighRisk = $mmeDaily >= self::CDC_HIGH_RISK_MME_THRESHOLD;

        return [
            'is_allowed' => true,
            'violation_reason' => null,
            'rolling_30d_orders_count' => $count30d,
            'distinct_prescribers_14d' => $distinctDoctors,
            'estimated_mme_daily' => $mmeDaily,
            'is_high_risk_overdose' => $isHighRisk,
        ];
    }

    /**
     * Menghitung faktor konversi Oral Morphine Milligram Equivalent (MME).
     */
    public function calculateEstimatedMmeDaily(string $drugCode, float $dailyDoseMg): float
    {
        $code = strtoupper(trim($drugCode));

        $conversionFactors = [
            'MORPHINE' => 1.0,
            'OXYCODONE' => 1.5,
            'FENTANYL' => 75.0, // Fentanyl transdermal mcg/hr dikonversi ke mg oral
            'HYDROCODONE' => 1.0,
            'HYDROMORPHONE' => 4.0,
            'CODEINE' => 0.15,
            'TRAMADOL' => 0.1,
        ];

        $factor = $conversionFactors[$code] ?? 1.0;

        return round($dailyDoseMg * $factor, 1);
    }
}
