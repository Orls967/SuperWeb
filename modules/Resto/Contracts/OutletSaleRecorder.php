<?php

declare(strict_types=1);

namespace Modules\Resto\Contracts;

interface OutletSaleRecorder
{
    /**
     * @param list<array{sku: string, qty: int}> $lines
     * @return array{order_id: int, total_minor: int, cogs_minor: int}
     */
    public function record(int $outletId, array $lines, int $tabRef, string $idempotencyKey): array;
}

