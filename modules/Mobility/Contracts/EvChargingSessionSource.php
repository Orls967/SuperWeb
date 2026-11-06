<?php

declare(strict_types=1);

namespace Modules\Mobility\Contracts;

use Carbon\CarbonImmutable;

interface EvChargingSessionSource
{
    /**
     * Mengambil daftar sesi pengisian EV pada site asset tertentu dalam rentang waktu.
     *
     * @return list<array{
     *     id: int,
     *     started_at: string,
     *     ended_at: string,
     *     kwh_milli: int,
     *     flexible: bool
     * }> Daftar sesi diurutkan berdasarkan started_at ASC, id ASC
     */
    public function sessionsBetween(int $siteAssetId, CarbonImmutable $from, CarbonImmutable $to): array;
}
