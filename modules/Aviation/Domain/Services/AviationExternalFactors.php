<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Services;

use Carbon\CarbonImmutable;
use Modules\Aviation\Domain\Models\AviationExternalFactor;

/**
 * G7-D51 & T-5: AviationExternalFactors
 *
 * Gerbang tunggal pembacaan faktor eksternal aviasi dari tabel avi_external_factors.
 * Menyediakan parameter berversi untuk harga avtur, penutupan bandara abu vulkanik,
 * dan cuaca ekstrem tanpa hard-coding.
 */
class AviationExternalFactors
{
    public function getAvturPriceIdrPerLiter(?CarbonImmutable $date = null): int
    {
        $d = $date ?? CarbonImmutable::now();
        $record = AviationExternalFactor::where('factor_key', 'avtur_price_idr_per_liter')
            ->where('effective_from', '<=', $d->toDateTimeString())
            ->orderByDesc('effective_from')
            ->first();

        return $record ? (int) $record->factor_value : 14500;
    }

    public function isAirportClosedDueToVolcano(string $iataCode, ?CarbonImmutable $date = null): bool
    {
        $d = $date ?? CarbonImmutable::now();
        $record = AviationExternalFactor::where('factor_key', "volcano_closure_{$iataCode}")
            ->where('effective_from', '<=', $d->toDateTimeString())
            ->orderByDesc('effective_from')
            ->first();

        return $record ? ($record->factor_value === 'closed' || $record->factor_value === '1') : false;
    }

    public function getEmissionFactorKgCo2ePerKgFuel(?CarbonImmutable $date = null): float
    {
        return 3.16; // Standar ICAO / MEPC avtur
    }

    public function recordFactor(
        string $key,
        string|int $value,
        CarbonImmutable $effectiveFrom,
        string $source = 'BMKG / Hubud',
        string $label = 'regulasi'
    ): AviationExternalFactor {
        return AviationExternalFactor::create([
            'factor_key' => $key,
            'factor_value' => (string) $value,
            'effective_from' => $effectiveFrom->toDateTimeString(),
            'source' => $source,
            'label' => $label,
        ]);
    }
}

