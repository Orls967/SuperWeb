<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Jalur Pengalihan Arus & Evakuasi Darurat Kerumunan (Incident Egress Route).
 */
final readonly class IncidentEgressRoute
{
    /**
     * @param  string  $routeCode  Kode unik rute evakuasi
     * @param  int  $originZoneId  Zona awal titik kumpul / bahaya
     * @param  int  $destinationZoneId  Zona tujuan yang aman
     * @param  int  $routeWidthCentimeters  Lebar efektif koridor dalam cm
     * @param  int  $maxFlowRatePersonsPerMinute  Kapasitas aliran maksimum orang per menit
     * @param  bool  $isPrimaryCorridor  Apakah koridor evakuasi utama
     */
    public function __construct(
        public string $routeCode,
        public int $originZoneId,
        public int $destinationZoneId,
        public int $routeWidthCentimeters,
        public int $maxFlowRatePersonsPerMinute,
        public bool $isPrimaryCorridor
    ) {
        if ($this->routeWidthCentimeters < 100) {
            throw new InvalidArgumentException('Lebar koridor evakuasi minimum adalah 100 cm.');
        }
        if ($this->maxFlowRatePersonsPerMinute <= 0) {
            throw new InvalidArgumentException('Kapasitas aliran per menit harus bernilai positif.');
        }
    }

    /**
     * Hitung estimasi waktu pengalihan massa dalam menit untuk headcount tertentu.
     */
    public function estimateClearingDurationMinutes(int $headcountToEvacuate): int
    {
        if ($headcountToEvacuate <= 0) {
            return 0;
        }

        return (int) ceil($headcountToEvacuate / $this->maxFlowRatePersonsPerMinute);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'route_code' => $this->routeCode,
            'origin_zone_id' => $this->originZoneId,
            'destination_zone_id' => $this->destinationZoneId,
            'route_width_cm' => $this->routeWidthCentimeters,
            'max_flow_rate_ppm' => $this->maxFlowRatePersonsPerMinute,
            'is_primary' => $this->isPrimaryCorridor,
        ];
    }
}
