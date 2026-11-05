<?php

declare(strict_types=1);

namespace Modules\Rwa\Contracts;

use Carbon\CarbonImmutable;

interface CatBondTokenRegistry
{
    /**
     * Daftarkan cat bond sebagai aset RWA dan kembalikan ID aset RWA.
     */
    public function registerBond(string $assetCode, int $principalIdr, int $units, string $idempotencyKey): int;

    /**
     * Catat reduksi pokok (write-down) saat trigger bencana terpenuhi.
     */
    public function writeDown(string $assetCode, int $principalReductionIdr, CarbonImmutable $at, string $idempotencyKey): void;
}

