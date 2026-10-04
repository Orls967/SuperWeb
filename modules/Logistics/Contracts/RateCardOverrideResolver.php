<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * Titik injeksi "rate card kontrak" (Fase 29.6).
 *
 * Diikat oleh modul Contract; modul Logistics hanya tahu interface sehingga
 * tidak mengimpor Domain modul lain (aturan batas arsitektur).
 *
 * Parameter memakai string nilai enum (bukan tipe enum Domain) agar
 * implementor di modul lain tidak perlu mengimpor Domain Logistics.
 */
interface RateCardOverrideResolver
{
    /**
     * @param  string  $serviceLevel  nilai ServiceLevel enum ('regular', 'express', dst.)
     * @param  string  $mode  nilai TransportMode enum ('road', 'sea', dst.)
     * @return int|null ID lgx_rate_cards yang harus didahulukan, atau null bila tidak ada override
     */
    public function resolve(
        int $shipperId,
        string $serviceLevel,
        string $mode
    ): ?int;
}
