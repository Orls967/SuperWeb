<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Application\DTOs\InaCbgCalculationDTO;
use Modules\Hospital\Domain\Models\InaCbgTariff;

/**
 * Layanan Domain Pengelompokan & Kalkulasi Tarif INA-CBGs (Indonesia Case Base Groups).
 * Mengimplementasikan pengelompokan kode CBG berdasarkan Severity Level (I, II, III),
 * kalkulasi base rate kelas RS, dan perhitungan selisih bayar kenaikan kelas rawat
 * sesuai Permenkes RI Nomor 3 Tahun 2023.
 */
class InaCbgGroupingService
{
    /**
     * Peta pengelompokan diagnosa ICD-10 utama ke prefix CBG.
     *
     * @var array<string, string>
     */
    private const DIAGNOSIS_TO_CBG_MAP = [
        'I21' => 'I-4-10', // Acute Myocardial Infarction
        'I50' => 'I-4-12', // Heart Failure
        'E11' => 'E-4-10', // Type 2 Diabetes Mellitus
        'J18' => 'J-4-16', // Pneumonia
        'A09' => 'K-4-14', // Infectious Gastroenteritis
        'O80' => 'O-6-10', // Normal Delivery
        'O82' => 'O-6-12', // Caesarean Section
        'S06' => 'N-4-10', // Intracranial Injury
        'K35' => 'K-1-10', // Acute Appendicitis
        'N18' => 'N-4-14', // Chronic Kidney Disease
    ];

    /**
     * Hitung tarif INA-CBGs dan selisih kenaikan kelas rawat inap.
     *
     * @param  string  $primaryDiagnosisCode  Kode ICD-10 diagnosa utama (e.g. 'I21.0')
     * @param  array  $secondaryDiagnosisCodes  Daftar kode komplikasi / komorbiditas
     * @param  array  $procedureCodes  Daftar kode tindakan medis ICD-9-CM
     * @param  string  $entitledClass  Kelas hak rawat peserta (CLASS_1, CLASS_2, CLASS_3)
     * @param  string  $actualClass  Kelas aktual ranjang yang ditempati pasien (CLASS_1, CLASS_2, CLASS_3, VIP)
     * @param  string  $hospitalType  Tipe/Kelas Rumah Sakit (A, B, C, D)
     *
     * @phpstan-param list<string> $secondaryDiagnosisCodes
     * @phpstan-param list<string> $procedureCodes
     */
    public function groupAndCalculateTariff(
        string $primaryDiagnosisCode,
        array $secondaryDiagnosisCodes,
        array $procedureCodes,
        string $entitledClass,
        string $actualClass,
        string $hospitalType = 'B'
    ): InaCbgCalculationDTO {
        $cleanDiag = strtoupper(trim(explode('.', $primaryDiagnosisCode)[0]));
        $prefix = self::DIAGNOSIS_TO_CBG_MAP[$cleanDiag] ?? 'Z-4-10';

        // Tentukan Severity Level (I = Ringan, II = Sedang, III = Berat)
        $severityLevel = $this->determineSeverityLevel($secondaryDiagnosisCodes, $procedureCodes);
        $fullCbgCode = "{$prefix}-{$severityLevel}";

        // Cari master tarif INA-CBG dari basis data atau fallback tarif terstandarisasi
        $cbgTariff = InaCbgTariff::where('cbg_code', $fullCbgCode)
            ->where('hospital_class', strtoupper($hospitalType))
            ->where('is_active', true)
            ->first();

        if (! $cbgTariff) {
            $cbgTariff = $this->generateStandardFallbackTariff($fullCbgCode, $cleanDiag, $severityLevel, $hospitalType);
        }

        $entitledClassTariff = $this->resolveClassTariff($cbgTariff, $entitledClass);
        $actualClassTariff = $this->resolveClassTariff($cbgTariff, $actualClass);

        // Hitung selisih naik kelas (Excess Upgrade) sesuai Permenkes 3/2023
        $upgradeExcessIdr = $cbgTariff->calculateClassUpgradeExcess($entitledClass, $actualClass);
        $isTopUpCapped = in_array(strtoupper($actualClass), ['VIP', 'VVIP', 'EXECUTIVE'], true);

        return new InaCbgCalculationDTO(
            cbgCode: $fullCbgCode,
            cbgDescription: $cbgTariff->description,
            severityLevel: $severityLevel,
            baseTariffIdr: $cbgTariff->base_tariff_idr,
            entitledClassTariffIdr: $entitledClassTariff,
            actualClassTariffIdr: $actualClassTariff,
            classUpgradeExcessIdr: $upgradeExcessIdr,
            primaryDiagnosisCode: $primaryDiagnosisCode,
            secondaryDiagnosisCodes: $secondaryDiagnosisCodes,
            procedureCodes: $procedureCodes,
            isTopUpCapped: $isTopUpCapped,
        );
    }

    /**
     * Menentukan tingkat keparahan (Severity Level) berdasarkan jumlah dan sifat diagnosa sekunder.
     *
     *
     * @phpstan-param list<string> $secondaryDiagnoses
     * @phpstan-param list<string> $procedures
     */
    private function determineSeverityLevel(array $secondaryDiagnoses, array $procedures): string
    {
        $comorbidityCount = count($secondaryDiagnoses);
        $procedureCount = count($procedures);

        // Kasus berat jika komplikasi >= 3 atau tindakan bedah mayor
        if ($comorbidityCount >= 3 || $procedureCount >= 2) {
            return 'III';
        }

        // Kasus sedang jika ada 1 atau 2 komorbiditas
        if ($comorbidityCount >= 1 || $procedureCount === 1) {
            return 'II';
        }

        return 'I';
    }

    private function resolveClassTariff(InaCbgTariff $tariff, string $bedClass): int
    {
        return match (strtoupper($bedClass)) {
            'CLASS_1' => $tariff->class_1_tariff_idr,
            'CLASS_2' => $tariff->class_2_tariff_idr,
            'CLASS_3' => $tariff->class_3_tariff_idr,
            'VIP', 'VVIP', 'EXECUTIVE' => (int) round($tariff->class_1_tariff_idr * 1.35),
            default => $tariff->base_tariff_idr,
        };
    }

    private function generateStandardFallbackTariff(
        string $cbgCode,
        string $diagPrefix,
        string $severity,
        string $hospitalClass
    ): InaCbgTariff {
        $multiplier = match ($severity) {
            'III' => 1.85,
            'II' => 1.35,
            default => 1.0,
        };

        $baseRate = match ($diagPrefix) {
            'I21', 'I50' => 12_500_000,
            'O82', 'K35', 'S06' => 8_500_000,
            'E11', 'J18', 'N18' => 6_500_000,
            default => 4_500_000,
        };

        $hospitalMultiplier = match (strtoupper($hospitalClass)) {
            'A' => 1.2,
            'B' => 1.0,
            'C' => 0.85,
            default => 0.75,
        };

        $finalBase = (int) round($baseRate * $multiplier * $hospitalMultiplier);
        $class3 = (int) round($finalBase * 0.9);
        $class2 = (int) round($finalBase * 1.1);
        $class1 = (int) round($finalBase * 1.35);
        $vipMaxTopup = (int) round($class1 * 0.75);

        return new InaCbgTariff([
            'cbg_code' => $cbgCode,
            'description' => "Paket INA-CBG {$cbgCode} Kasus Spesialistik {$severity}",
            'care_type' => 'INPATIENT',
            'severity_level' => $severity,
            'hospital_class' => strtoupper($hospitalClass),
            'base_tariff_idr' => $finalBase,
            'class_3_tariff_idr' => $class3,
            'class_2_tariff_idr' => $class2,
            'class_1_tariff_idr' => $class1,
            'vip_max_topup_idr' => $vipMaxTopup,
            'is_active' => true,
        ]);
    }
}
