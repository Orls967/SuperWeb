<?php

declare(strict_types=1);

namespace Modules\Logistics\Contracts;

/**
 * Kontrak K4: Akses master kapal dan kontainer dari modul Logistics (KONSEP §5F, G6-P1).
 */
interface VesselRegistry
{
    /**
     * @return array{id: int, imo: string, name: string, loa_cm: int}|null
     */
    public function findByImo(string $imo): ?array;

    /**
     * @return array{id: int, number: string, iso_type: string, line_party_id: int}|null
     */
    public function containerById(int $containerId): ?array;
}
