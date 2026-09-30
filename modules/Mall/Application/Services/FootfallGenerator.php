<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Services;

use Carbon\Carbon;
use Modules\Mall\Domain\Models\FootfallCount;
use Modules\Mall\Domain\Models\Property;

/**
 * Pembangkit data kunjungan mall untuk kebutuhan demo dan analitik.
 *
 * Pola kunjungan mengikuti perilaku mall Indonesia: sepi saat mall baru buka,
 * memuncak pada jam makan siang dan jam 17-21, serta jauh lebih ramai di akhir pekan.
 */
class FootfallGenerator
{
    public const GATES = ['Gate Utama', 'Gate Basement', 'Gate Food Court'];

    /** Bobot kunjungan per jam operasional (10:00 - 22:00). */
    private const HOURLY_WEIGHTS = [
        10 => 0.35,
        11 => 0.50,
        12 => 0.75,
        13 => 0.70,
        14 => 0.60,
        15 => 0.65,
        16 => 0.80,
        17 => 1.00,
        18 => 1.10,
        19 => 1.20,
        20 => 1.05,
        21 => 0.70,
        22 => 0.25,
    ];

    /**
     * Isi data kunjungan untuk rentang tanggal tertentu.
     *
     * @return int jumlah baris jam-gate yang ditulis
     */
    public function generate(Property $property, Carbon $startDate, Carbon $endDate, int $baseVisitorsPerHour = 420): int
    {
        $rows = [];
        $now = now();
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $isWeekend = $cursor->isSaturday() || $cursor->isSunday();
            $dayMultiplier = $isWeekend ? 1.85 : 1.0;

            foreach (self::HOURLY_WEIGHTS as $hour => $weight) {
                foreach (self::GATES as $index => $gate) {
                    // Gate utama menyerap porsi terbesar, gate lain lebih kecil
                    $gateShare = [0.55, 0.28, 0.17][$index];
                    $expected = $baseVisitorsPerHour * $weight * $dayMultiplier * $gateShare;

                    // Variasi acak +/-15% supaya grafik tidak terlihat sintetis
                    $variation = random_int(85, 115) / 100;
                    $visitorsIn = (int) round($expected * $variation);

                    // Pengunjung keluar tertinggal dari yang masuk pada jam-jam awal
                    $exitRatio = $hour <= 12 ? 0.55 : ($hour >= 20 ? 1.35 : 0.95);
                    $visitorsOut = (int) round($visitorsIn * $exitRatio);

                    $rows[] = [
                        'property_id' => $property->id,
                        'date' => $cursor->toDateString(),
                        'hour' => $hour,
                        'gate_name' => $gate,
                        'in_count' => $visitorsIn,
                        'out_count' => $visitorsOut,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            $cursor->addDay();
        }

        // Tulis dalam potongan agar hemat memori pada rentang 12 bulan
        foreach (array_chunk($rows, 500) as $chunk) {
            FootfallCount::upsert(
                $chunk,
                ['property_id', 'date', 'hour', 'gate_name'],
                ['in_count', 'out_count', 'updated_at']
            );
        }

        return count($rows);
    }
}
