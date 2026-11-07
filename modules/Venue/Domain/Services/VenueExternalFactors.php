<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Services;

use Modules\Venue\Domain\Models\VenueExternalFactor;

final class VenueExternalFactors
{
    public static function getFactor(string $key, int $default = 0): int
    {
        $factor = VenueExternalFactor::where('factor_key', $key)
            ->orderByDesc('version')
            ->first();

        return $factor ? (int) $factor->numeric_value_scaled : $default;
    }

    public static function getRainProbabilityBps(string $city = 'Badung', int $default = 2000): int
    {
        return self::getFactor("weather.rain_probability_bps.{$city}", $default);
    }

    public static function getPbjtRateBps(string $city = 'Badung', string $type = 'NIGHTCLUB', int $default = 4000): int
    {
        return self::getFactor("tax.pbjt_rate_bps.{$city}.{$type}", $default);
    }

    public static function getMinAge(string $venueType = 'BEACH_CLUB', int $default = 21): int
    {
        return self::getFactor("compliance.min_age.{$venueType}", $default);
    }

    public static function getSafetyMarginBps(int $default = 500): int
    {
        return self::getFactor('safety.zone_margin_bps', $default);
    }
}
