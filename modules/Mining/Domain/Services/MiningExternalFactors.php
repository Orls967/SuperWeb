<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Core\Contracts\ClockInterface;
use Modules\Mining\Domain\Models\MiningExternalFactor;

/**
 * Gerbang Terpusat Pembacaan Faktor Eksternal Pertambangan (T-5, G7-D30).
 * Menghubungkan seluruh kalkulasi cuaca, kurs valuta, harga patokan HBA/HMA/HPB,
 * kebijakan ekspor, kuota DMO, ambang anomali BBM, dan tarif royalti berversi.
 *
 * Catatan ADR-0093 (G7-D30): Kelas gerbang ini dirancang sebagai antarmuka tunggal
 * yang akan digantikan oleh adaptor ExternalFactorReader (W8-P10) tanpa mengubah pemanggil.
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
     * @return array{rainfall_mm: int, downtime_hours: int, source: string, label: string, version: int}
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
        } elseif ($rainfallMm > 25) {
            $downtimeHours = 1;
        }

        return [
            'rainfall_mm' => $rainfallMm,
            'downtime_hours' => $downtimeHours,
            'source' => $factor?->source ?? 'BMKG Stasiun Meteorologi Tambang',
            'label' => $factor?->label ?? 'asumsi_simulasi',
            'version' => $factor ? (int) $factor->version : 1,
        ];
    }

    /**
     * Mengambil ambang kelebihan pemakaian BBM kontraktor dalam basis points (default fixture 1.500 bps = 15%).
     */
    public function getFuelAnomalyThresholdBps(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('fuel_anomaly_threshold_bps', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 1500;
    }

    /**
     * Mengambil harga acuan solar industri per liter dalam rupiah (default Rp 15.000/L).
     */
    public function getIndustrialDieselPriceIdrPerLiter(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('diesel_price_idr_per_liter', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 15000;
    }

    /**
     * Mengambil harga patokan batubara (HBA) dalam sen USD per ton.
     */
    public function getHbaCoalPriceUsdCents(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('hba_coal_usd_per_ton', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 8500;
    }

    /**
     * Mengambil harga mineral acuan nikel (HMA Ni) dalam sen USD per ton logam.
     */
    public function getHmaNickelPriceUsdCents(?CarbonImmutable $date = null): int
    {
        $factor = $this->resolveFactor('hma_nickel_usd_per_ton', $date);

        return $factor ? (int) $factor->numeric_value_scaled : 1850000;
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
     * Mengambil kebijakan ekspor dan alasan regulasi.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function getExportPolicy(string $commodity, ?CarbonImmutable $date = null): array
    {
        $banned = $this->isExportBanned($commodity, $date);

        return [
            'allowed' => ! $banned,
            'reason' => $banned ? "Kebijakan larangan ekspor {$commodity} mentah per regulasi hilirisasi ESDM" : 'Ekspor diizinkan',
        ];
    }

    /**
     * Mengambil versi parameter eksternal aktif berdasarkan tanggal berlakunya.
     */
    public function resolveFactor(string $key, ?CarbonImmutable $date = null): ?MiningExternalFactor
    {
        $clock = $this->clock ?? app(ClockInterface::class);
        $nowDate = $date ?? CarbonImmutable::instance($clock->now());
        $dateStr = $nowDate->toDateString();

        return MiningExternalFactor::where('factor_key', $key)
            ->where('effective_from', '<=', $dateStr)
            ->orderBy('effective_from', 'desc')
            ->orderBy('version', 'desc')
            ->first();
    }
}
