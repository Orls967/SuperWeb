<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Nilai Objek Kapasitas Pemrosesan Stasiun Bar & Antrean F&B.
 */
final readonly class ServiceStationThroughput
{
    /**
     * @param  int  $stationNumber  Nomor stasiun kasir/bar
     * @param  int  $activeBartendersCount  Jumlah bartender/kasir aktif
     * @param  int  $ordersCompletedLastHour  Jumlah pesanan terselesaikan dalam 1 jam terakhir
     * @param  int  $grossSalesLastHourIdr  Nilai omset dalam Rupiah 1 jam terakhir
     * @param  int  $averagePreparationSeconds  Rata-rata durasi penyiapan pesanan (detik)
     * @param  int  $wpsScore  Skor kepuasan layanan saat ini (0 - 100)
     */
    public function __construct(
        public int $stationNumber,
        public int $activeBartendersCount,
        public int $ordersCompletedLastHour,
        public int $grossSalesLastHourIdr,
        public int $averagePreparationSeconds,
        public int $wpsScore
    ) {
        if ($this->stationNumber <= 0) {
            throw new InvalidArgumentException('Station number harus bernilai positif integer.');
        }
        if ($this->activeBartendersCount < 0) {
            throw new InvalidArgumentException('Active bartenders count tidak boleh negatif.');
        }
    }

    /**
     * Hitung laju efisiensi throughput per bartender per jam.
     */
    public function calculateThroughputPerStaff(): int
    {
        if ($this->activeBartendersCount <= 0) {
            return 0;
        }

        return (int) intdiv($this->ordersCompletedLastHour, $this->activeBartendersCount);
    }

    /**
     * Apakah stasiun ini beroperasi di bawah target minimum kepuasan pengunjung (WPS < 60).
     */
    public function isUnderperforming(): bool
    {
        return $this->wpsScore < 60;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'station_number' => $this->stationNumber,
            'active_bartenders_count' => $this->activeBartendersCount,
            'orders_completed_last_hour' => $this->ordersCompletedLastHour,
            'gross_sales_last_hour_idr' => $this->grossSalesLastHourIdr,
            'average_prep_seconds' => $this->averagePreparationSeconds,
            'wps_score' => $this->wpsScore,
            'throughput_per_staff' => $this->calculateThroughputPerStaff(),
            'underperforming' => $this->isUnderperforming(),
        ];
    }
}
