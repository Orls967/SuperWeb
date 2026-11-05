<?php

declare(strict_types=1);

namespace Modules\Agri\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak Antar-Modul G7-K1: Pembaca indeks vegetasi satelit (NDVI) dan curah hujan untuk asuransi parametrik tani.
 */
interface NdviIndexReader
{
    /**
     * @return array{plot_code: string, ndvi_bps: int, rainfall_deficit_bps: int, is_drought_triggered: bool, source: string}
     */
    public function readIndex(string $plotCode, CarbonImmutable $date): array;
}
