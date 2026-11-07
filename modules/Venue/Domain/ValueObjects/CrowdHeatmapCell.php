<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Sel Titik Peta Panas Kerumunan (SVG Heatmap Cell).
 * Merepresentasikan koordinat poligon, tingkat densitas, headcount riil,
 * serta status pembatasan akses gerbang turnstile.
 */
final readonly class CrowdHeatmapCell
{
    public const string COLOR_GREEN = '#10B981';

    public const string COLOR_YELLOW = '#F59E0B';

    public const string COLOR_ORANGE = '#F97316';

    public const string COLOR_RED = '#EF4444';

    public const string COLOR_EMERGENCY_FLASH = '#991B1B';

    /**
     * @param  int  $zoneId  Identitas zona
     * @param  string  $zoneCode  Kode zona
     * @param  string  $zoneName  Nama zona venue
     * @param  int  $currentHeadcount  Jumlah fisik orang saat ini
     * @param  int  $capacityLimit  Batas kapasitas legal
     * @param  int  $densitySqmBps  Densitas per meter persegi (bps)
     * @param  string  $svgPolygonCoordinates  Koordinat poligon SVG (misal "50,50 150,50 150,150 50,150")
     * @param  string  $colorHex  Kode warna HEX visualisasi
     * @param  bool  $isGateRestricted  Apakah gerbang masuk dibatasi
     */
    public function __construct(
        public int $zoneId,
        public string $zoneCode,
        public string $zoneName,
        public int $currentHeadcount,
        public int $capacityLimit,
        public int $densitySqmBps,
        public string $svgPolygonCoordinates,
        public string $colorHex,
        public bool $isGateRestricted
    ) {
        if ($this->currentHeadcount < 0) {
            throw new InvalidArgumentException('Current headcount tidak boleh bernilai negatif.');
        }
        if ($this->capacityLimit <= 0) {
            throw new InvalidArgumentException('Capacity limit harus lebih besar dari nol.');
        }
    }

    /**
     * Tentukan warna status keselamatan secara deterministik berdasarkan rasio okupansi dan densitas.
     */
    public static function determineStatusColor(int $occupancyRatioBps, int $densityBps, bool $isRestricted): string
    {
        return match (true) {
            $isRestricted || $occupancyRatioBps >= 9500 || $densityBps >= 45000 => self::COLOR_RED,
            $occupancyRatioBps >= 8500 || $densityBps >= 35000 => self::COLOR_ORANGE,
            $occupancyRatioBps >= 7000 || $densityBps >= 25000 => self::COLOR_YELLOW,
            default => self::COLOR_GREEN,
        };
    }

    /**
     * Konversi representasi menjadi array terstruktur untuk rendering SVG dashboard.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $occupancyPct = round(($this->currentHeadcount / max(1, $this->capacityLimit)) * 100, 1);

        return [
            'zone_id' => $this->zoneId,
            'zone_code' => $this->zoneCode,
            'zone_name' => $this->zoneName,
            'current_headcount' => $this->currentHeadcount,
            'capacity_limit' => $this->capacityLimit,
            'occupancy_percentage' => $occupancyPct,
            'density_sqm_bps' => $this->densitySqmBps,
            'density_sqm_display' => sprintf('%.2f org/m²', $this->densitySqmBps / 10000),
            'svg_polygon' => $this->svgPolygonCoordinates,
            'color_hex' => $this->colorHex,
            'gate_restricted' => $this->isGateRestricted,
        ];
    }
}
