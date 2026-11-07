<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * G7-D43 Gerbang Kepekaan Eksternal HotelExternalFactors
 *
 * Membaca data parameter berversi dari tabel `htl_external_factors`:
 * - Indeks permintaan pasar / festival event kota
 * - Indeks kedatangan wisman / travel advisory
 * - Kurs mata uang (USD, dsb)
 * - Cuaca / bencana
 * - Tarif PBJT perda per kota (fixture 10%)
 * - Pungutan wisatawan asing Bali (Rp 150.000)
 */
final class HotelExternalFactors
{
    /**
     * @param string $factorCode Kode faktor (mis. 'PBJT_HOTEL_RATE_BPS', 'BALI_FOREIGN_TOURIST_LEVY_IDR', 'NO_SHOW_DISTRIBUTION_BPS')
     * @param string $city Kota tujuan (default 'Bali' atau 'All')
     * @param mixed $default Nilai default jika belum tercatat
     * @return mixed
     */
    public static function get(string $factorCode, string $city = 'All', mixed $default = null): mixed
    {
        $factor = DB::table('htl_external_factors')
            ->where('factor_code', $factorCode)
            ->where(function ($query) use ($city): void {
                $query->where('city', $city)
                    ->orWhere('city', 'All');
            })
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN city = ? THEN 0 ELSE 1 END", [$city])
            ->orderByDesc('version')
            ->first();

        if ($factor === null) {
            return $default;
        }

        $decoded = json_decode($factor->value_json, true);
        return $decoded ?? $factor->value_json;
    }

    public static function getPbjtRateBps(string $city = 'All'): int
    {
        return (int) self::get('PBJT_HOTEL_RATE_BPS', $city, 1000); // 10%
    }

    public static function getServiceChargeBps(string $city = 'All'): int
    {
        return (int) self::get('SERVICE_CHARGE_BPS', $city, 1000); // 10%
    }

    public static function getBaliLevyIdr(): int
    {
        return (int) self::get('BALI_FOREIGN_TOURIST_LEVY_IDR', 'Bali', 150_000);
    }

    public static function getApoaDeadlineHours(): int
    {
        return (int) self::get('APOA_DEADLINE_HOURS', 'All', 24);
    }

    /**
     * @return list<int>
     */
    public static function getNoShowDistributionBps(): array
    {
        /** @var list<int>|null $dist */
        $dist = self::get('NO_SHOW_DISTRIBUTION_BPS', 'All', [500, 1000, 2000, 2500, 2000, 1500, 500]);
        return $dist;
    }

    public static function isCityEventActive(string $city): bool
    {
        return (bool) self::get('CITY_EVENT_ACTIVE', $city, false);
    }
}
