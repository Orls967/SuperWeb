<?php

declare(strict_types=1);

namespace Modules\Egy\Application\Services;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Layanan Pemeliharaan Preventif Genset Darurat & Penuaan Stok Bahan Bakar Solar.
 * Memantau degradasi kimia solar akibat penyimpanan lama (> 6 bulan),
 * serta menjadwalkan overhaul berkala (penggantian filter BBM, pelumas mesin, dan baterai starter).
 */
final class GeneratorFuelStockAndMaintenanceScheduleService
{
    /**
     * Hitung indeks penuaan stok bahan bakar solar (Fuel Aging & Stability Index).
     *
     * @param  int  $storedFuelLiters  Volume solar di tangki timbun (Liter)
     * @param  Carbon  $lastFuelPolishingDate  Tanggal terakhir pembersihan / sirkulasi bahan bakar
     * @param  int  $totalRuntimeHoursCumulative  Total jam jalan akumulatif mesin
     * @return array<string, mixed>
     */
    public function assessFuelAndMaintenanceStatus(
        string $generatorAssetCode,
        int $storedFuelLiters,
        Carbon $lastFuelPolishingDate,
        int $totalRuntimeHoursCumulative
    ): array {
        if ($storedFuelLiters < 0 || $totalRuntimeHoursCumulative < 0) {
            throw new InvalidArgumentException('Volume bahan bakar dan jam jalan akumulatif tidak boleh negatif.');
        }

        $daysSincePolishing = (int) $lastFuelPolishingDate->diffInDays(now());

        // Degradasi solar dimulai setelah 180 hari (6 bulan) tanpa aditif stabilizer / fuel polishing
        // Skor stabilitas bahan bakar dalam basis points (10.000 = sangat murni / baru, 0 = terkontaminasi parah)
        $fuelStabilityScoreBps = max(1000, 10000 - ($daysSincePolishing * 25)); // Penurunan ~25 bps per hari setelah simpan

        $needsFuelPolishing = $daysSincePolishing > 180 || $fuelStabilityScoreBps < 6000;

        // Jadwal pemeliharaan preventif berdasarkan jam jalan mesin (Standar ISO 8528):
        // 1. Ganti oli mesin & filter solar setiap 250 jam jalan
        $hoursSinceLastOilChange = $totalRuntimeHoursCumulative % 250;
        $hoursToNextOilChange = 250 - $hoursSinceLastOilChange;
        $needsOilChange = $hoursSinceLastOilChange >= 240;

        // 2. Overhaul injektor & kalibrasi governor setiap 1.500 jam jalan
        $hoursSinceMajorService = $totalRuntimeHoursCumulative % 1500;
        $hoursToMajorService = 1500 - $hoursSinceMajorService;
        $needsMajorService = $hoursSinceMajorService >= 1400;

        $recommendedActions = [];
        if ($needsFuelPolishing) {
            $recommendedActions[] = 'Sirkulasi & filtrasi pembersihan bahan bakar solar (Fuel Polishing) wajib dilakukan.';
        }
        if ($needsOilChange) {
            $recommendedActions[] = 'Penggantian oli pelumas mesin dan filter oli segera dijadwalkan (< 10 jam tersisa).';
        }
        if ($needsMajorService) {
            $recommendedActions[] = 'Jadwalkan overhaul injektor bahan bakar dan kalibrasi sistem kontrol generator.';
        }
        if (empty($recommendedActions)) {
            $recommendedActions[] = 'Kondisi operasional genset prima; lanjutkan uji pemanasan mingguan berkala.';
        }

        $payload = sprintf(
            'GENSET-MAINT|ASSET:%s|FUEL_L:%d|DAYS:%d|STAB_BPS:%d|HOURS:%d|ACTIONS:%d',
            $generatorAssetCode,
            $storedFuelLiters,
            $daysSincePolishing,
            $fuelStabilityScoreBps,
            $totalRuntimeHoursCumulative,
            count($recommendedActions)
        );
        $maintenanceHash = hash('sha256', $payload);

        return [
            'generator_asset_code' => $generatorAssetCode,
            'stored_fuel_liters' => $storedFuelLiters,
            'days_since_fuel_polishing' => $daysSincePolishing,
            'fuel_stability_score_bps' => $fuelStabilityScoreBps,
            'fuel_stability_percentage' => ($fuelStabilityScoreBps / 100).'%',
            'needs_fuel_polishing' => $needsFuelPolishing,
            'total_runtime_hours' => $totalRuntimeHoursCumulative,
            'hours_to_next_oil_change' => $hoursToNextOilChange,
            'hours_to_major_overhaul' => $hoursToMajorService,
            'recommended_actions' => $recommendedActions,
            'maintenance_hash' => $maintenanceHash,
        ];
    }
}
