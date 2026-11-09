<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Modules\Hospital\Domain\Models\PharmacyBranch;
use Modules\Hospital\Domain\ValueObjects\GpsCoordinates;
use RuntimeException;

/**
 * Pharmacy Geospatial Routing & Multi-Branch Allocation Engine.
 * Menemukan titik pemenuhan e-resep optimal di antara jaringan 500+ apotek:
 * 1. Perhitungan jarak Haversine presisi tinggi dari koordinat GPS tempat tinggal pasien.
 * 2. Filter fasilitas penyimpanan rantai dingin untuk sediaan termolabil.
 * 3. Pemeriksaan ketersediaan stok aktif FEFO (First-Expired, First-Out).
 * 4. Estimasi waktu tempuh pengiriman (SLA < 2 jam untuk radius metropolitan 25 km).
 */
class PharmacyGeospatialRoutingEngine
{
    private const AVERAGE_COURIER_SPEED_KMH = 25.0; // Kecepatan rata-rata kurir sepeda motor perkotaan

    private const DISPATCH_HANDLING_MINUTES = 20;

    public function __construct(
        protected FefoPharmacyDispensingEngine $fefoEngine
    ) {}

    /**
     * Menemukan apotek terbaik berdasarkan kedekatan jarak dan ketersediaan stok.
     *
     * @return array{
     *     branch: PharmacyBranch,
     *     distance_km: float,
     *     estimated_transit_minutes: int,
     *     total_sla_minutes: int,
     *     stock_available: int,
     *     is_partner_network: bool
     * }
     */
    public function findOptimalBranch(
        float $patientLat,
        float $patientLon,
        string $drugCode,
        int $requiredQuantity,
        bool $requiresColdChain = false,
        float $maxSearchRadiusKm = 25.0
    ): array {
        $patientCoords = new GpsCoordinates($patientLat, $patientLon);

        $query = PharmacyBranch::where('is_active', true);
        if ($requiresColdChain) {
            $query->where('has_cold_chain_storage', true);
        }

        $branches = $query->get();
        if ($branches->isEmpty()) {
            throw new RuntimeException('Tidak ditemukan cabang apotek aktif dengan fasilitas yang memenuhi syarat.');
        }

        $candidates = [];

        foreach ($branches as $branch) {
            $bLat = (float) ($branch->latitude ?? -6.2088);
            $bLon = (float) ($branch->longitude ?? 106.8456);
            $branchCoords = new GpsCoordinates($bLat, $bLon);

            $distanceKm = $patientCoords->distanceTo($branchCoords);
            if ($distanceKm > $maxSearchRadiusKm) {
                continue;
            }

            $availableStock = $this->fefoEngine->getAvailableStock($branch->id, $drugCode);
            if ($availableStock >= $requiredQuantity) {
                $transitMinutes = (int) ceil(($distanceKm / self::AVERAGE_COURIER_SPEED_KMH) * 60);
                $totalSlaMinutes = $transitMinutes + self::DISPATCH_HANDLING_MINUTES;

                $candidates[] = [
                    'branch' => $branch,
                    'distance_km' => $distanceKm,
                    'estimated_transit_minutes' => $transitMinutes,
                    'total_sla_minutes' => $totalSlaMinutes,
                    'stock_available' => $availableStock,
                    'is_partner_network' => $branch->type !== 'INTERNAL',
                ];
            }
        }

        if (empty($candidates)) {
            // Fallback: cari cabang internal rumah sakit terpusat (Pusat Logistik RS)
            $centralBranch = PharmacyBranch::where('is_active', true)
                ->where('type', 'INTERNAL')
                ->first();

            if (! $centralBranch) {
                $centralBranch = $branches->first();
            }

            $bLat = (float) ($centralBranch->latitude ?? -6.2088);
            $bLon = (float) ($centralBranch->longitude ?? 106.8456);
            $dist = $patientCoords->distanceTo(new GpsCoordinates($bLat, $bLon));
            $stock = $this->fefoEngine->getAvailableStock($centralBranch->id, $drugCode);

            return [
                'branch' => $centralBranch,
                'distance_km' => $dist,
                'estimated_transit_minutes' => (int) ceil(($dist / self::AVERAGE_COURIER_SPEED_KMH) * 60),
                'total_sla_minutes' => (int) ceil(($dist / self::AVERAGE_COURIER_SPEED_KMH) * 60) + self::DISPATCH_HANDLING_MINUTES,
                'stock_available' => $stock,
                'is_partner_network' => false,
            ];
        }

        // Urutkan kandidat berdasarkan: 1. Jarak terdekat (km), 2. Internal RS diprioritaskan atas Rekanan
        usort($candidates, function ($a, $b) {
            if ($a['is_partner_network'] !== $b['is_partner_network']) {
                return $a['is_partner_network'] ? 1 : -1;
            }

            return $a['distance_km'] <=> $b['distance_km'];
        });

        return $candidates[0];
    }
}
