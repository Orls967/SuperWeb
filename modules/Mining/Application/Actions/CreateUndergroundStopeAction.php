<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningUndergroundStope;

/**
 * Domain Action: Pembuatan dan Validasi Blok Stope Tambang Bawah Tanah.
 * Menghitung parameter dimensi stope, metode penambangan (cut-and-fill vs sublevel stoping),
 * Rock Mass Rating (RMR), dan batas tonase cadangan bijih bawah tanah.
 */
class CreateUndergroundStopeAction
{
    /**
     * @param  array{
     *     stope_code: string,
     *     level_name: string,
     *     mining_method?: string,
     *     planned_tonnage: float,
     *     ore_grade: float,
     *     rock_mass_rating: int,
     *     length_meters: float,
     *     width_meters: float,
     *     height_meters: float,
     *     rock_density_t_m3?: float,
     *     support_system?: string,
     *     description?: string|null
     * } $data
     */
    public function execute(array $data): MiningUndergroundStope
    {
        $this->validateDimensions($data);

        $length = (float) $data['length_meters'];
        $width = (float) $data['width_meters'];
        $height = (float) $data['height_meters'];
        $density = (float) ($data['rock_density_t_m3'] ?? 2.65);

        $stopeVolumeM3 = round($length * $width * $height, 2);
        $theoreticalTonnage = round($stopeVolumeM3 * $density, 2);

        $rmr = (int) $data['rock_mass_rating'];
        $miningMethod = $data['mining_method'] ?? $this->recommendMiningMethod($rmr);
        $supportSystem = $data['support_system'] ?? $this->recommendGroundSupport($rmr);

        $stope = new MiningUndergroundStope;
        $stope->fill([
            'stope_code' => strtoupper(trim($data['stope_code'])),
            'level_name' => trim($data['level_name']),
            'mining_method' => $miningMethod,
            'status' => 'DEVELOPMENT',
            'planned_tonnage' => $data['planned_tonnage'] > 0 ? (float) $data['planned_tonnage'] : $theoreticalTonnage,
            'actual_tonnage' => 0.0,
            'ore_grade' => (float) $data['ore_grade'],
            'rock_mass_rating' => $rmr,
            'length_meters' => $length,
            'width_meters' => $width,
            'height_meters' => $height,
            'volume_m3' => $stopeVolumeM3,
            'support_system' => $supportSystem,
            'metadata' => [
                'rock_density_t_m3' => $density,
                'theoretical_tonnage' => $theoreticalTonnage,
                'rock_mass_class' => $this->classifyRockMass($rmr),
                'created_by_action' => static::class,
                'created_at_iso' => now()->toIso8601String(),
            ],
        ]);

        $stope->save();

        return $stope;
    }

    /**
     * Memvalidasi parameter fisik stope.
     *
     * @param  array<string, mixed>  $data
     */
    private function validateDimensions(array $data): void
    {
        if (empty($data['stope_code'])) {
            throw new InvalidArgumentException('Stope code tidak boleh kosong.');
        }

        if (empty($data['level_name'])) {
            throw new InvalidArgumentException('Level name bawah tanah tidak boleh kosong.');
        }

        if (($data['length_meters'] ?? 0) <= 0 || ($data['width_meters'] ?? 0) <= 0 || ($data['height_meters'] ?? 0) <= 0) {
            throw new InvalidArgumentException('Dimensi stope (panjang, lebar, tinggi) harus lebih besar dari 0 meter.');
        }

        $rmr = $data['rock_mass_rating'] ?? 0;
        if ($rmr < 0 || $rmr > 100) {
            throw new InvalidArgumentException('Bieniawski Rock Mass Rating (RMR) harus berada dalam rentang 0 hingga 100.');
        }

        if (($data['ore_grade'] ?? 0) < 0) {
            throw new InvalidArgumentException('Kadar bijih (ore grade) tidak boleh negatif.');
        }
    }

    /**
     * Merekomendasikan metode tambang berdasarkan RMR Bieniawski (1989).
     */
    private function recommendMiningMethod(int $rmr): string
    {
        if ($rmr >= 70) {
            return 'SUBLEVEL_OPEN_STOPING';
        }
        if ($rmr >= 45) {
            return 'CUT_AND_FILL_STOPING';
        }

        return 'SQUARE_SET_OR_TOP_SLICING';
    }

    /**
     * Merekomendasikan sistem penyangga batuan (ground support).
     */
    private function recommendGroundSupport(int $rmr): string
    {
        if ($rmr >= 81) {
            return 'SPOT_BOLTING_AS_REQUIRED';
        }
        if ($rmr >= 61) {
            return 'SYSTEMATIC_BOLTS_2M_SPACING_WITH_MESH';
        }
        if ($rmr >= 41) {
            return 'SYSTEMATIC_BOLTS_1_5M_SPACING_FIBER_SHOTCRETE_50MM';
        }
        if ($rmr >= 21) {
            return 'SYSTEMATIC_BOLTS_1M_SPACING_SHOTCRETE_100MM_STEEL_SETS';
        }

        return 'HEAVY_STEEL_SETS_CAST_CONCRETE_INVERT_ARCH';
    }

    /**
     * Mengklasifikasikan kelas massa batuan.
     */
    private function classifyRockMass(int $rmr): string
    {
        if ($rmr >= 81) {
            return 'CLASS_I_VERY_GOOD_ROCK';
        }
        if ($rmr >= 61) {
            return 'CLASS_II_GOOD_ROCK';
        }
        if ($rmr >= 41) {
            return 'CLASS_III_FAIR_ROCK';
        }
        if ($rmr >= 21) {
            return 'CLASS_IV_POOR_ROCK';
        }

        return 'CLASS_V_VERY_POOR_ROCK';
    }
}
