<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Algorithms;

final class PatientDischargeReadinessScorer
{
    /**
     * Menilai kesiapan pasien rawat inap untuk dipulangkan (Discharge Readiness Score)
     * berdasarkan stabilitas hemodinamik, toleransi oral, ambulasi, kepatuhan obat,
     * serta dukungan perawat rumah (home care / caregiver support).
     *
     * @param array{
     *     vitals_stable_24h: bool,
     *     oral_intake_adequate: bool,
     *     independent_or_assisted_ambulation: bool,
     *     pain_controlled: bool,
     *     iv_medications_converted_to_oral: bool,
     *     caregiver_trained: bool,
     *     transportation_arranged: bool,
     *     pending_critical_lab_results: bool,
     *     news2_score: int
     * } $criteria
     * @return array{
     *     readiness_score_pct: int,
     *     is_ready_for_discharge: bool,
     *     discharge_status: string,
     *     blocking_reasons: list<string>,
     *     recommendations: list<string>,
     *     certificate: array{
     *         algorithm: string,
     *         version: string,
     *         input_hash: string,
     *         signature: string
     *     }
     * }
     */
    public function evaluate(array $criteria): array
    {
        $points = 0;
        $maxPoints = 100;
        $blockingReasons = [];
        $recommendations = [];

        // 1. Stabilitas vital 24 jam (bobot 25)
        if (! empty($criteria['vitals_stable_24h']) && ($criteria['news2_score'] ?? 0) <= 2) {
            $points += 25;
        } else {
            $blockingReasons[] = 'Tanda vital belum stabil dalam 24 jam terakhir atau skor NEWS2 > 2';
            $recommendations[] = 'Observasi lanjutan tanda vital minimal 12 jam';
        }

        // 2. Toleransi nutrisi & cairan oral (bobot 15)
        if (! empty($criteria['oral_intake_adequate'])) {
            $points += 15;
        } else {
            $blockingReasons[] = 'Asupan oral belum adekuat; risiko dehidrasi / malnutrisi post-KRS';
            $recommendations[] = 'Konsultasi ahli gizi klinik dan monitoring retensi enteral';
        }

        // 3. Mobilisasi & ambulasi (bobot 15)
        if (! empty($criteria['independent_or_assisted_ambulation'])) {
            $points += 15;
        } else {
            $blockingReasons[] = 'Pasien belum dapat mobilisasi aman ke tingkat dasar rawat jalan';
            $recommendations[] = 'Rujuk fisioterapi untuk latihan transfer posisi aman';
        }

        // 4. Manajemen nyeri terkontrol (bobot 15)
        if (! empty($criteria['pain_controlled'])) {
            $points += 15;
        } else {
            $blockingReasons[] = 'Nyeri skala sedang/berat belum terkontrol dengan analgesik oral';
            $recommendations[] = 'Titrasi dosis analgesik oral sebelum pelepasan infus';
        }

        // 5. Konversi obat oral (bobot 10)
        if (! empty($criteria['iv_medications_converted_to_oral'])) {
            $points += 10;
        } else {
            $blockingReasons[] = 'Masih memerlukan medikasi intravena aktif';
            $recommendations[] = 'Pertimbangkan switch terapi oral atau home IV therapy';
        }

        // 6. Kesiapan pendamping / caregiver (bobot 10)
        if (! empty($criteria['caregiver_trained'])) {
            $points += 10;
        } else {
            $recommendations[] = 'Edukasi dan pelatihan caregiver terkait perawatan luka / konsumsi obat';
        }

        // 7. Pengaturan transportasi (bobot 10)
        if (! empty($criteria['transportation_arranged'])) {
            $points += 10;
        } else {
            $recommendations[] = 'Konfirmasi armada transportasi pulang (ambulans vs kendaraan pribadi)';
        }

        // Hard blocker: pending lab kritis
        if (! empty($criteria['pending_critical_lab_results'])) {
            $blockingReasons[] = 'Terdapat hasil pemeriksaan laboratorium kritis yang belum diverifikasi DPJP';
            $recommendations[] = 'Tunggu verifikasi hasil lab kultur/enzim sebelum menandatangani resume medis';
        }

        $isReady = (count($blockingReasons) === 0) && ($points >= 80);
        $status = $isReady ? 'READY_FOR_DISCHARGE' : ($points >= 60 ? 'CONDITIONAL_PREPARATION' : 'NOT_READY');

        $inputSerialized = json_encode($criteria, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);
        $sigPayload = "PDRS-V1:{$points}:{$status}:".($isReady ? '1' : '0').":{$inputHash}";
        $signature = hash_hmac('sha256', $sigPayload, 'HSP_SECRET_DISCHARGE_READINESS_2026');

        return [
            'readiness_score_pct' => $points,
            'is_ready_for_discharge' => $isReady,
            'discharge_status' => $status,
            'blocking_reasons' => $blockingReasons,
            'recommendations' => $recommendations,
            'certificate' => [
                'algorithm' => 'PatientDischargeReadinessScorer',
                'version' => '1.0.0',
                'input_hash' => $inputHash,
                'signature' => $signature,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $criteria
     */
    public function verifyCertificate(array $result, array $criteria): bool
    {
        $inputSerialized = json_encode($criteria, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $inputHash = hash('sha256', $inputSerialized);

        $cert = $result['certificate'] ?? [];
        if (($cert['input_hash'] ?? '') !== $inputHash) {
            return false;
        }

        $points = $result['readiness_score_pct'] ?? 0;
        $status = $result['discharge_status'] ?? '';
        $isReady = ! empty($result['is_ready_for_discharge']) ? '1' : '0';
        $expectedSigPayload = "PDRS-V1:{$points}:{$status}:{$isReady}:{$inputHash}";
        $expectedSig = hash_hmac('sha256', $expectedSigPayload, 'HSP_SECRET_DISCHARGE_READINESS_2026');

        return hash_equals($expectedSig, (string) ($cert['signature'] ?? ''));
    }
}
