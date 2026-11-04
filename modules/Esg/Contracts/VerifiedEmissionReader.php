<?php

declare(strict_types=1);

namespace Modules\Esg\Contracts;

use Carbon\CarbonImmutable;

/**
 * K7 (KATALOG KONTRAK G6) — emisi spesifik produk yang sudah diverifikasi pihak ketiga.
 */
interface VerifiedEmissionReader
{
    /**
     * @return array{t_co2e_per_t:string, verification_ref:string, verified_at:string}|null null bila tidak ada data terverifikasi
     */
    public function specificEmissionPerTonne(string $productCode, string $originCountry, CarbonImmutable $at): ?array;
}
