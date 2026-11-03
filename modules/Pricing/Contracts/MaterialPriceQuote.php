<?php

declare(strict_types=1);

namespace Modules\Pricing\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak kuotasi harga material daur ulang per kilogram (K2).
 */
interface MaterialPriceQuote
{
    /**
     * Mengembalikan harga per kilogram dalam integer rupiah pada waktu tertentu.
     * Mengembalikan null bila harga belum ditetapkan.
     */
    public function pricePerKgIdr(string $materialCode, string $gradeCode, CarbonImmutable $at): ?int;
}

