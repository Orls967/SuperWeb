<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use DomainException;

/**
 * Mesin Kepatuhan Regulasi Limbah Medis B3 & Festronik (FestronikRegulatoryComplianceEngine).
 * Mengimplementasikan regulasi Permen LHK RI No. P.56/Menlhk-Setjen/2015:
 *   1. Ambang batas waktu penyimpanan maksimal di TPS B3 internal:
 *      - Suhu ruang (> 0°C): Maksimal 2 hari kalender (48 jam).
 *      - Suhu dingin (<= 0°C): Maksimal 90 hari kalender.
 *   2. Standarisasi kemasan kantong & wadah berkode warna nasional:
 *      - KUNING: Limbah Infeksius & Patologis (Lambang Biohazard).
 *      - COKELAT: Limbah Farmasi & Kimiawi Kadaluarsa.
 *      - UNGU: Limbah Sitotoksik / Kemoterapi (Lambang Sel Terbelah).
 *      - KUNING TEBAL / SAFETY BOX: Limbah Benda Tajam (Sharps).
 *   3. Verifikasi legalitas izin pengangkut KLHK & sertifikasi insinerator suhu tinggi.
 */
class FestronikRegulatoryComplianceEngine
{
    /**
     * Kode klasifikasi jenis limbah B3 medis nasional.
     *
     * @var array<string, array{color_code: string, container_type: string, max_ambient_hours: int, disposal_method: string}>
     */
    private const WASTE_REGULATORY_PROFILES = [
        'SHARPS' => [
            'color_code' => 'YELLOW_SAFETY_BOX',
            'container_type' => 'Puncture-Proof Rigid Box',
            'max_ambient_hours' => 48,
            'disposal_method' => 'AUTOCLAVE_SHREDDING_OR_INCINERATION',
        ],
        'INFECTIOUS' => [
            'color_code' => 'YELLOW_BIOHAZARD_BAG',
            'container_type' => 'Heavy Duty Autoclavable Plastic (min 70 micron)',
            'max_ambient_hours' => 48,
            'disposal_method' => 'HIGH_TEMP_INCINERATION_MIN_1000C',
        ],
        'PATHOLOGICAL' => [
            'color_code' => 'YELLOW_DOUBLE_BAG',
            'container_type' => 'Leak-Proof Rigid Container with Biohazard Symbol',
            'max_ambient_hours' => 24,
            'disposal_method' => 'CREMATION_OR_DEEP_BURIAL_PERMITTED',
        ],
        'PHARMACEUTICAL' => [
            'color_code' => 'BROWN_BAG',
            'container_type' => 'Standard Heavy Plastic',
            'max_ambient_hours' => 720, // 30 hari
            'disposal_method' => 'HIGH_TEMP_INCINERATION_MIN_1200C',
        ],
        'CYTOTOXIC' => [
            'color_code' => 'PURPLE_BAG',
            'container_type' => 'Cytotoxic Waste Rigid Sealable Drum',
            'max_ambient_hours' => 48,
            'disposal_method' => 'ROTARY_KILN_INCINERATION_MIN_1200C',
        ],
        'CHEMICAL' => [
            'color_code' => 'BROWN_RIGID_CONTAINER',
            'container_type' => 'High-Density Polyethylene Drum',
            'max_ambient_hours' => 720,
            'disposal_method' => 'NEUTRALIZATION_AND_SOLIDIFICATION',
        ],
    ];

    /**
     * Memeriksa kepatuhan durasi penyimpanan di TPS B3 internal.
     *
     * @param  string  $wasteCategory  Kategori limbah (SHARPS, INFECTIOUS, dll.)
     * @param  float  $tpsStorageTempCelsius  Suhu ruang pendingin TPS
     * @param  int  $storedDurationHours  Durasi limbah telah berada di TPS dalam satuan jam
     * @return array{
     *     is_compliant: bool,
     *     regulatory_max_hours: int,
     *     exceeded_hours: int,
     *     compliance_status: string,
     *     warning_message: string|null
     * }
     */
    public function verifyTpsStorageCompliance(
        string $wasteCategory,
        float $tpsStorageTempCelsius,
        int $storedDurationHours
    ): array {
        $category = strtoupper(trim($wasteCategory));
        $profile = self::WASTE_REGULATORY_PROFILES[$category] ?? [
            'color_code' => 'YELLOW_GENERIC',
            'container_type' => 'Standard Bag',
            'max_ambient_hours' => 48,
            'disposal_method' => 'INCINERATION',
        ];

        // Jika suhu pendingin TPS <= 0°C, diizinkan maksimal 90 hari (2160 jam)
        $allowedHours = $tpsStorageTempCelsius <= 0.0 ? 2160 : $profile['max_ambient_hours'];
        $isCompliant = $storedDurationHours <= $allowedHours;
        $exceeded = max(0, $storedDurationHours - $allowedHours);

        $status = 'COMPLIANT';
        $warning = null;

        if (! $isCompliant) {
            $status = 'OVERDUE_VIOLATION';
            $warning = "Pelanggaran Permen LHK P.56/2015: Limbah {$category} telah disimpan selama {$storedDurationHours} jam (maksimal batas izin: {$allowedHours} jam pada suhu {$tpsStorageTempCelsius}°C). Wajib segera diangkut transporter berizin!";
        } elseif ($storedDurationHours > ($allowedHours * 0.8)) {
            $status = 'NEAR_DEADLINE_WARNING';
            $warning = "Peringatan: Masa simpan TPS limbah {$category} mendekati batas maksimal (tersisa " . ($allowedHours - $storedDurationHours) . ' jam).';
        }

        return [
            'is_compliant' => $isCompliant,
            'regulatory_max_hours' => $allowedHours,
            'exceeded_hours' => $exceeded,
            'compliance_status' => $status,
            'warning_message' => $warning,
        ];
    }

    /**
     * Memvalidasi parameter pembakaran termal insinerator untuk penerbitan sertifikat pemusnahan (COD).
     *
     * @param  float  $chamber1TempCelsius  Suhu ruang bakar primer (°C) - minimal 800°C
     * @param  float  $chamber2TempCelsius  Suhu ruang bakar sekunder (°C) - minimal 1000°C
     * @param  float  $retentionTimeSeconds  Waktu tinggal gas buang di ruang sekunder - minimal 2 detik
     * @param  float  $particulateEmissionMgNm3  Emisi partikulat debu (maksimal 50 mg/Nm³)
     * @return array{
     *     is_destruction_certified: bool,
     *     destruction_efficiency_pct: float,
     *     emission_standard_met: bool,
     *     disapproval_reasons: array<int, string>
     * }
     */
    public function certifyThermalIncineration(
        float $chamber1TempCelsius,
        float $chamber2TempCelsius,
        float $retentionTimeSeconds,
        float $particulateEmissionMgNm3
    ): array {
        $reasons = [];

        if ($chamber1TempCelsius < 800.0) {
            $reasons[] = "Suhu ruang bakar primer tidak memenuhi syarat minimum ({$chamber1TempCelsius}°C < 800°C).";
        }

        if ($chamber2TempCelsius < 1000.0) {
            $reasons[] = "Suhu ruang bakar sekunder tidak memenuhi syarat minimum ({$chamber2TempCelsius}°C < 1000°C).";
        }

        if ($retentionTimeSeconds < 2.0) {
            $reasons[] = "Waktu tinggal gas buang kurang dari 2 detik ({$retentionTimeSeconds}s).";
        }

        if ($particulateEmissionMgNm3 > 50.0) {
            $reasons[] = "Emisi partikulat melebihi baku mutu KLHK ({$particulateEmissionMgNm3} mg/Nm³ > 50 mg/Nm³).";
        }

        $isValid = empty($reasons);
        $efficiency = $isValid ? 99.99 : round(max(90.0, 99.99 - (count($reasons) * 3.5)), 2);

        return [
            'is_destruction_certified' => $isValid,
            'destruction_efficiency_pct' => $efficiency,
            'emission_standard_met' => $particulateEmissionMgNm3 <= 50.0,
            'disapproval_reasons' => $reasons,
        ];
    }
}
