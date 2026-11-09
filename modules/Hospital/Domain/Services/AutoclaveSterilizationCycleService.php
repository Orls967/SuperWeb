<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Illuminate\Support\Str;

/**
 * Layanan Domain Siklus Sterilisasi Termal Uap Bertekanan Autoclave & Dekontaminasi Limbah (AutoclaveSterilizationCycleService).
 * Memantau parameter fisik siklus sterilisasi limbah infeksius dan instrumen medis kamar bedah:
 *   1. Pemenuhan Standar Suhu & Tekanan (121°C pada 1.2 bar selama 30 menit, atau 134°C pada 2.1 bar selama 15 menit).
 *   2. Validasi Indikator Biologis (Geobacillus stearothermophilus spore kill rate >= 10^6).
 *   3. Validasi Indikator Kimiawi Integrator Kelas 5 (ISO 11140-1).
 *   4. Penerbitan Sertifikat Dekontaminasi Limbah Medis Sebelum Shredding (Pencacahan Mekanis).
 */
class AutoclaveSterilizationCycleService
{
    /**
     * Parameter profil siklus standar sterilisasi autoclave.
     *
     * @var array<string, array{target_temp_c: float, target_pressure_bar: float, min_holding_minutes: int}>
     */
    private const STERILIZATION_PROFILES = [
        'CYCLE_GRAVITY_121C' => [
            'target_temp_c' => 121.0,
            'target_pressure_bar' => 1.15,
            'min_holding_minutes' => 30,
        ],
        'CYCLE_PREVACUUM_134C' => [
            'target_temp_c' => 134.0,
            'target_pressure_bar' => 2.10,
            'min_holding_minutes' => 15,
        ],
        'CYCLE_HAZARDOUS_WASTE_DECONTAMINATION' => [
            'target_temp_c' => 135.0,
            'target_pressure_bar' => 2.20,
            'min_holding_minutes' => 45, // Durasi lebih lama untuk inaktivasi total spora limbah klinis
        ],
    ];

    /**
     * Mengevaluasi dan memverifikasi kelulusan siklus sterilisasi autoclave.
     *
     * @param  string  $profileCode  Kode profil siklus (e.g. CYCLE_HAZARDOUS_WASTE_DECONTAMINATION)
     * @param  float  $achievedTemperatureCelsius  Suhu puncak yang tercapai
     * @param  float  $achievedPressureBar  Tekanan uap yang tercapai
     * @param  int  $holdingDurationMinutes  Durasi penahanan suhu konstan
     * @param  bool  $biologicalIndicatorPassed  Apakah hasil kultur spora Bacillus negatif (spora mati 100%)
     * @param  bool  $chemicalIntegratorColorChanged  Apakah indikator kimiawi berubah warna sempurna
     * @return array{
     *     cycle_log_id: string,
     *     profile_code: string,
     *     is_cycle_successful: bool,
     *     sterility_assurance_level: float,
     *     biological_verification: string,
     *     chemical_verification: string,
     *     failure_reasons: list<string>
     * }
     */
    public function evaluateSterilizationCycle(
        string $profileCode,
        float $achievedTemperatureCelsius,
        float $achievedPressureBar,
        int $holdingDurationMinutes,
        bool $biologicalIndicatorPassed,
        bool $chemicalIntegratorColorChanged
    ): array {
        $profile = self::STERILIZATION_PROFILES[strtoupper($profileCode)] ?? self::STERILIZATION_PROFILES['CYCLE_GRAVITY_121C'];
        $cycleId = 'STER-'.date('Ymd').'-'.strtoupper(Str::random(6));

        $failures = [];

        if ($achievedTemperatureCelsius < $profile['target_temp_c']) {
            $failures[] = "Suhu sterilisasi tidak mencapai target minimum ({$achievedTemperatureCelsius}°C < {$profile['target_temp_c']}°C).";
        }

        if ($achievedPressureBar < ($profile['target_pressure_bar'] * 0.95)) {
            $failures[] = "Tekanan uap jenuh tidak memadai ({$achievedPressureBar} bar < {$profile['target_pressure_bar']} bar).";
        }

        if ($holdingDurationMinutes < $profile['min_holding_minutes']) {
            $failures[] = "Durasi penahanan siklus kurang dari batas minimum ({$holdingDurationMinutes} menit < {$profile['min_holding_minutes']} menit).";
        }

        if (! $biologicalIndicatorPassed) {
            $failures[] = 'Indikator biologis Geobacillus stearothermophilus menunjukkan pertumbuhan aktif (spora gagal diinaktivasi).';
        }

        if (! $chemicalIntegratorColorChanged) {
            $failures[] = 'Indikator kimiawi kelas 5 tidak menunjukkan integrasi parameter uap sempurna.';
        }

        $isSuccess = empty($failures);
        $sal = $isSuccess ? 1.0e-6 : 1.0e-2; // Sterility Assurance Level 10^-6 standar internasional

        return [
            'cycle_log_id' => $cycleId,
            'profile_code' => $profileCode,
            'is_cycle_successful' => $isSuccess,
            'sterility_assurance_level' => $sal,
            'biological_verification' => $biologicalIndicatorPassed ? 'NO_SPORE_GROWTH_STERILE' : 'SPORE_POSITIVE_CONTAMINATED',
            'chemical_verification' => $chemicalIntegratorColorChanged ? 'END_POINT_REACHED' : 'INCOMPLETE_REACTION',
            'failure_reasons' => $failures,
        ];
    }
}
