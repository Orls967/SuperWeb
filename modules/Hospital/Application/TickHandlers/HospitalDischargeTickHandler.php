<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\TickHandlers;

use Carbon\CarbonInterface;
use Modules\Core\Contracts\SimTickHandlerInterface;
use Modules\Hospital\Domain\Models\BillingEpisode;

/**
 * Handler simulasi harian untuk modul Hospital: menginspeksi episode rawat inap,
 * otomatisasi discharge pasien terjadwal, dan perhitungan metrik utilisasi ranjang deterministik.
 */
class HospitalDischargeTickHandler implements SimTickHandlerInterface
{
    public function name(): string
    {
        return 'hospital_discharge';
    }

    /**
     * @return list<string>
     */
    public function dependencies(): array
    {
        return []; // Simpul awal tanpa dependensi sebelum modul lain
    }

    public function shouldRun(CarbonInterface $virtualNow, int $dayNumber): bool
    {
        return true;
    }

    public function handle(CarbonInterface $virtualNow, int $dayNumber, ?callable $commandRunner = null): array
    {
        // 1. Ambil episode aktif
        $activeEpisodes = BillingEpisode::where('status', 'active')->get();
        $totalActive = $activeEpisodes->count();

        $dischargedCount = 0;
        $totalBilledIdr = 0;

        foreach ($activeEpisodes as $episode) {
            // Evaluasi apakah episode telah melampaui estimasi durasi rawat (default 3 hari)
            $admittedAt = $episode->admitted_at ?? $virtualNow->copy()->subDays(3);
            $daysInWard = (int) $virtualNow->diffInDays($admittedAt);

            if ($daysInWard >= 3) {
                // Pasien siap discharge jika sudah memenuhi masa rawat
                $episode->status = 'discharged';
                $episode->discharged_at = $virtualNow->toDateTimeString();
                $episode->save();

                $dischargedCount++;
                $totalBilledIdr += (int) ($episode->total_amount ?? 1500000);
            }
        }

        // 2. Metrik kapasitas deterministik
        $bedCapacity = 100;
        $remainingActive = max(0, $totalActive - $dischargedCount);
        $occupancyRate = round(($remainingActive / $bedCapacity) * 100, 2);

        return [
            'day' => $dayNumber,
            'virtual_date' => $virtualNow->toDateString(),
            'initial_active_episodes' => $totalActive,
            'discharged_count' => $dischargedCount,
            'remaining_active' => $remainingActive,
            'total_billed_idr' => $totalBilledIdr,
            'bed_occupancy_rate_pct' => $occupancyRate,
            'status' => 'ok',
        ];
    }
}
