<?php

declare(strict_types=1);

namespace Modules\Supplier\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Supplier\Contracts\SupplierRiskInterface;

class SupplierRiskService implements SupplierRiskInterface
{
    public function createRiskFlag(
        int $supplierId,
        string $type,
        string $severity,
        string $message,
        array $meta = []
    ): int {
        return (int) DB::table('sup_risk_flags')->insertGetId([
            'supplier_id' => $supplierId,
            'type' => $type,
            'severity' => $severity,
            'message' => $message,
            'meta' => json_encode($meta),
            'is_open' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
