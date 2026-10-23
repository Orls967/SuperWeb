<?php

declare(strict_types=1);

namespace Modules\Supplier\Contracts;

interface SupplierRiskInterface
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function createRiskFlag(
        int|string $supplierId,
        string $type,
        string $severity,
        string $message,
        array $meta = []
    ): int;
}
