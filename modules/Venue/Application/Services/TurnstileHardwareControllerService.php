<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\GateTurnstile;
use RuntimeException;

/**
 * Layanan Pengendali Perangkat Keras Turnstile & Pintu Gerbang (Turnstile HAL Service).
 * Mengontrol aktuator kunci magnetik, menghitung putaran tripod, dan mengimplementasikan
 * protokol fail-open penyelamatan darurat.
 */
final class TurnstileHardwareControllerService
{
    /**
     * Catat perputaran scan turnstile masuk yang sah.
     */
    public function recordSuccessfulEntry(string $gateCode): GateTurnstile
    {
        $turnstile = GateTurnstile::where('gate_code', $gateCode)->lockForUpdate()->firstOrFail();

        if ($turnstile->is_locked) {
            throw new RuntimeException(sprintf('Turnstile [%s] sedang terkunci mekanis.', $gateCode));
        }

        $turnstile->total_scans_in++;
        $turnstile->last_scan_at = Carbon::now();
        $turnstile->save();

        // Tingkatkan occupancy zona terkait
        $zone = EntertainmentZone::where('id', $turnstile->zone_id)->lockForUpdate()->first();
        if ($zone !== null) {
            $zone->current_occupancy++;
            $zone->save();
        }

        return $turnstile;
    }

    /**
     * Catat perputaran turnstile keluar pengunjung (Outflow).
     */
    public function recordSuccessfulExit(string $gateCode): GateTurnstile
    {
        $turnstile = GateTurnstile::where('gate_code', $gateCode)->lockForUpdate()->firstOrFail();

        $turnstile->total_scans_out++;
        $turnstile->last_scan_at = Carbon::now();
        $turnstile->save();

        // Kurangi occupancy zona terkait
        $zone = EntertainmentZone::where('id', $turnstile->zone_id)->lockForUpdate()->first();
        if ($zone !== null && $zone->current_occupancy > 0) {
            $zone->current_occupancy--;
            $zone->save();
        }

        return $turnstile;
    }

    /**
     * Kunci atau buka gerbang turnstile tertentu.
     */
    public function toggleGateLock(string $gateCode, bool $lockState): GateTurnstile
    {
        $turnstile = GateTurnstile::where('gate_code', $gateCode)->lockForUpdate()->firstOrFail();
        $turnstile->is_locked = $lockState;
        $turnstile->save();

        return $turnstile;
    }

    /**
     * Protokol Pelepasan Darurat Seluruh Pintu Keluar Zona (Fail-Open Emergency Release).
     *
     * @return int Jumlah gerbang yang berhasil dilepaskan kuncinya
     */
    public function releaseAllEmergencyExits(int $zoneId): int
    {
        return GateTurnstile::where('zone_id', $zoneId)
            ->where('is_emergency_exit', true)
            ->update(['is_locked' => false]);
    }

    /**
     * Dapatkan status kesehatan perangkat turnstile pada zona.
     *
     * @return Collection<int, array{gate_code: string, name: string, status: string, total_flow: int}>
     */
    public function getZoneTurnstileHealth(int $zoneId): Collection
    {
        return GateTurnstile::where('zone_id', $zoneId)
            ->get()
            ->map(fn (GateTurnstile $gt) => [
                'gate_code' => $gt->gate_code,
                'name' => $gt->gate_name,
                'status' => $gt->is_locked ? 'LOCKED' : 'READY',
                'type' => $gt->turnstile_type,
                'is_emergency' => $gt->is_emergency_exit,
                'total_flow' => $gt->total_scans_in + $gt->total_scans_out,
                'last_active' => $gt->last_scan_at?->diffForHumans() ?? 'Belum ada aktivitas',
            ]);
    }
}
