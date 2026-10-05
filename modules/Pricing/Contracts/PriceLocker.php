<?php

declare(strict_types=1);

namespace Modules\Pricing\Contracts;

/**
 * Titik injeksi kunci harga immutable (Fase 44.4) — dikonsumsi modul
 * Store/Distribution tanpa mengimpor Domain Pricing.
 */
interface PriceLocker
{
    /**
     * Kunci harga satu baris item pada suatu dokumen. Replay aman:
     * kunci yang sudah ada tidak ditulis ulang.
     *
     * @param  array{sku:string,qty:float,price_idr:int}  $line
     */
    public function lockLine(string $subjectType, string $subjectId, array $line, string $sourceKind, array $waterfall = [], ?string $reason = null): void;
}
