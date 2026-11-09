<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use InvalidArgumentException;

/**
 * HazardousSpillEmergencyProtocolEngine
 *
 * Mengatur respons tanggap darurat tumpahan limbah B3 medis, sitotoksik / kemoterapi,
 * dan cairan infeksius (Permen LHK No. P.56/2015 & SNI Keselamatan Kerja RS).
 * Menentukan klasifikasi tumpahan, radius isolasi, PPE yang diwajibkan,
 * dan prosedur dekontaminasi spesifik.
 */
class HazardousSpillEmergencyProtocolEngine
{
    public const SPILL_TYPE_INFECTIOUS_BLOOD = 'INFECTIOUS_BLOOD_BODY_FLUID';

    public const SPILL_TYPE_CYTOTOXIC_CHEMO = 'CYTOTOXIC_CHEMOTHERAPY';

    public const SPILL_TYPE_MERCURY_METALLIC = 'MERCURY_HEAVY_METAL';

    public const SPILL_TYPE_CHEMICAL_FORMALIN = 'CHEMICAL_FORMALIN_SOLVENT';

    public const SEVERITY_MINOR = 'MINOR_SPILL';

    public const SEVERITY_MAJOR = 'MAJOR_SPILL';

    public const SEVERITY_CATASTROPHIC = 'CATASTROPHIC_CONTAMINATION';

    /**
     * Tentukan klasifikasi insiden dan prosedur dekontaminasi.
     *
     * @param  string  $spillType  Tipe substansi tumpahan
     * @param  float  $volumeMilliliters  Estimasi volume tumpahan (mL)
     * @param  bool  $isAerosolized  Apakah tumpahan tersirkulasi atau berpotensi aerosol
     * @param  bool  $occurredInPublicArea  Apakah terjadi di koridor umum atau ruang pasien
     * @return array{
     *     spill_type: string,
     *     severity: string,
     *     isolation_radius_meters: float,
     *     requires_code_orange_alarm: bool,
     *     required_ppe: array<string>,
     *     neutralizing_agent: string,
     *     contact_time_minutes: int,
     *     waste_bin_type: string,
     *     reporting_mandatory_within_hours: int,
     *     action_checklist: array<string>
     * }
     */
    public function assessSpillEmergency(
        string $spillType,
        float $volumeMilliliters,
        bool $isAerosolized,
        bool $occurredInPublicArea
    ): array {
        if ($volumeMilliliters <= 0) {
            throw new InvalidArgumentException('Volume tumpahan harus lebih besar dari 0 mL');
        }

        $validTypes = [
            self::SPILL_TYPE_INFECTIOUS_BLOOD,
            self::SPILL_TYPE_CYTOTOXIC_CHEMO,
            self::SPILL_TYPE_MERCURY_METALLIC,
            self::SPILL_TYPE_CHEMICAL_FORMALIN,
        ];

        if (! in_array($spillType, $validTypes, true)) {
            throw new InvalidArgumentException("Tipe tumpahan tidak valid: {$spillType}");
        }

        // Tentukan severity
        $severity = self::SEVERITY_MINOR;
        if ($spillType === self::SPILL_TYPE_CYTOTOXIC_CHEMO && $volumeMilliliters >= 5.0) {
            $severity = self::SEVERITY_MAJOR;
        } elseif ($volumeMilliliters >= 1000.0 || $isAerosolized) {
            $severity = self::SEVERITY_CATASTROPHIC;
        } elseif ($volumeMilliliters >= 50.0 || ($occurredInPublicArea && $volumeMilliliters >= 10.0)) {
            $severity = self::SEVERITY_MAJOR;
        }

        // Hitung radius isolasi & kebutuhan alarm darurat K3
        $isolationRadiusMeters = 2.0;
        $requiresCodeOrangeAlarm = false;

        if ($severity === self::SEVERITY_CATASTROPHIC) {
            $isolationRadiusMeters = 15.0;
            $requiresCodeOrangeAlarm = true;
        } elseif ($severity === self::SEVERITY_MAJOR) {
            $isolationRadiusMeters = 5.0;
            $requiresCodeOrangeAlarm = ($spillType === self::SPILL_TYPE_CYTOTOXIC_CHEMO || $occurredInPublicArea);
        }

        // Aturan spesifik per substansi
        $requiredPpe = ['NITRILE_EXAM_GLOVES', 'SURGICAL_MASK', 'PROTECTIVE_GOWN'];
        $neutralizingAgent = 'SODIUM_HYPOCHLORITE_0_5_PERCENT';
        $contactTimeMinutes = 10;
        $wasteBinType = 'YELLOW_BAG_BIOHAZARD';

        switch ($spillType) {
            case self::SPILL_TYPE_CYTOTOXIC_CHEMO:
                $requiredPpe = ['DOUBLE_CHEMO_RATED_GLOVES', 'N95_RESPIRATOR_VALVED', 'CHEMO_IMPERMEABLE_GOWN', 'SAFETY_GOGGLES', 'SHOE_COVERS'];
                $neutralizingAgent = 'SODIUM_THIOSULFATE_OR_CYTOTOXIC_SPILL_PAD';
                $contactTimeMinutes = 15;
                $wasteBinType = 'PURPLE_BAG_CYTOTOXIC';
                break;

            case self::SPILL_TYPE_MERCURY_METALLIC:
                $requiredPpe = ['NITRILE_CHEMICAL_GLOVES', 'P100_MERCURY_VAPOR_RESPIRATOR', 'FACE_SHIELD'];
                $neutralizingAgent = 'SULFUR_POWDER_AMALGAMATION_MATRIX';
                $contactTimeMinutes = 30;
                $wasteBinType = 'BROWN_BAG_CHEMICAL_HEAVY_METAL';
                break;

            case self::SPILL_TYPE_CHEMICAL_FORMALIN:
                $requiredPpe = ['BUTYL_GLOVES', 'ORGANIC_VAPOR_RESPIRATOR', 'CHEMICAL_SPLASH_APRON'];
                $neutralizingAgent = 'AMMONIUM_CARBONATE_FORMALIN_NEUTRALIZER';
                $contactTimeMinutes = 20;
                $wasteBinType = 'BROWN_BAG_CHEMICAL_HEAVY_METAL';
                break;

            case self::SPILL_TYPE_INFECTIOUS_BLOOD:
            default:
                if ($severity !== self::SEVERITY_MINOR) {
                    $requiredPpe[] = 'FACE_SHIELD_VISOR';
                    $requiredPpe[] = 'WATERPROOF_BOOT_COVERS';
                }
                break;
        }

        $checklist = [
            '1. Amankan area sekitar dan pasang tanda bahaya Biohazard / Spillage.',
            '2. Kenakan seluruh APD sesuai SOP penanganan tumpahan.',
            '3. Batasi penyebaran cairan dengan absorbent pad dari arah luar ke dalam.',
            '4. Aplikasikan larutan netralisasi: '.$neutralizingAgent.' dan tunggu waktu kontak minimal '.$contactTimeMinutes.' menit.',
            '5. Angkat residu dan pad serapan menggunakan serokan dan spatula plastik sekali pakai.',
            '6. Masukkan seluruh material terdekontaminasi ke dalam wadah khusus: '.$wasteBinType.'.',
            '7. Pel dan bilas area tumpahan dengan air bersih dan desinfektan lantai RS.',
            '8. Timbang kantong limbah dan catat manifest Festronik dengan barcode darurat.',
        ];

        return [
            'spill_type' => $spillType,
            'severity' => $severity,
            'isolation_radius_meters' => $isolationRadiusMeters,
            'requires_code_orange_alarm' => $requiresCodeOrangeAlarm,
            'required_ppe' => $requiredPpe,
            'neutralizing_agent' => $neutralizingAgent,
            'contact_time_minutes' => $contactTimeMinutes,
            'waste_bin_type' => $wasteBinType,
            'reporting_mandatory_within_hours' => ($severity === self::SEVERITY_CATASTROPHIC) ? 1 : 24,
            'action_checklist' => $checklist,
        ];
    }
}
