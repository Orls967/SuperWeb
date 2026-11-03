<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

use Carbon\CarbonImmutable;

/**
 * Kontrak K5: Mengutip tarif Demurrage & Detention kontainer (KONSEP §5F, G6-P1).
 */
interface DemurrageDetentionQuote
{
    /**
     * @return array{demurrage_idr: int, detention_idr: int, free_days: int}
     */
    public function quote(int $containerId, CarbonImmutable $from, CarbonImmutable $to): array;
}
