<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Models\MiningExternalFactor;

/**
 * Gerbang Terpusat Pembacaan Faktor Eksternal Pertambangan (T-5, G7-D30).
 * Menghubungkan seluruh kalkulasi cuaca, kurs valuta, harga patokan HBA/HMA/HPB,
 * kebijakan ekspor, kuota DMO, dan tarif royalti berversi.
 */
class MiningExternalFactors
{
    public function __construct(
        private readonly ?ClockInterface $clock = null
    ) {}

    /**
     * Mengambil curah hujan harian (mm) dan mengonversinya ke jam kerja hilang (downtime).
     * Aturan: Hujan > 50 mm memotong 3 jam/shift; Hujan > 100 mm memotong 6 jam/shift.
     *
     * @return array{rainfall_mm: int, downtime_hours: int, source: string, label: string}
     */
    public function getRainfallDowntime(string $siteCode, ?CarbonImmutable $date = null): array
    {
        $factor = $this->resolveFactor("weather_rainfall_{$siteCode}", $date)
            ?? $this->resolveFactor('weather_rainfall_default', $date);

        $rainfallMm = $factor ? (int) $factor->numeric_value_scaled : 0;

        $downtimeHours = 0;
        if ($rainfallMm > 100) {
            $downtimeHours = 6;
        } elseif ($rainfallMm > 50) {
            $downtimeHours = 3;
        }

        return [
            'rainfall_mm' => $rainfallMm,
            'downtime_hours' => $downtimeHours,
            'source' => $factor?->source ?? 'BMKG Stasiun Meteorologi Tambang',
            'label' => $factor?->label ?? 'asumsi_simulasi',
        ];
    }

    /**
     * Mengambil harga patokan batubara (HBA) dalam sen USD per ton.
     */
    public function getHbaCoalPriceUsdCents(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('hba_coal_usd_per_ton', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 8500; // USD 85.00
    }

    /**
     * Mengambil kurs konversi USD ke IDR (skala 1e0 / rupiah integer).
     */
    public function getUsdIdrExchangeRate(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('usd_idr_exchange_rate', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 16250;
    }

    /**
     * Mengambil persentase kewajiban pasar domestik (DMO) dalam basis points (misal 2500 = 25%).
     */
    public function getDmoRequirementBps(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('dmo_percentage_bps', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 2500;
    }

    /**
     * Memeriksa apakah terdapat kebijakan larangan ekspor untuk komoditas tertentu.
     */
    public function isExportBanned(string $commodity, ?CarbonImmutable $date = null): bool
    {
        $factor = $this->resolveFactor("export_policy_{$commodity}", $date);

        return $factor ? (bool) $factor->numeric_value_scaled : false;
    }

    /**
     * Mengambil versi parameter eksternal aktif berdasarkan tanggal berlakunya.
     */
    public function resolveFactor(string $key, ?CarbonImmutable $date = null): ?MiningExternalFactor
    {
        $nowDate = $date ?? ($this->clock ? CarbonImmutable::instance($this->clock->now()) : CarbonImmutable::now());
        $dateStr = $nowDate->toDateString();

        return MiningExternalFactor::where('factor_key', $key)
            ->where('effective_from', '<=', $dateStr)
            ->orderBy('effective_from', 'desc')
            ->orderBy('version', 'desc')
            ->first();
    }
}
