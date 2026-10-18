<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Vending\Domain\Models\VendingUnit;

final class VndAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'vnd:audit';
    }

    public function corrupt(): string
    {
        // Corrupt vending unit state: stock exceeds capacity and sales mismatch
        VendingUnit::create([
            'unit_code' => 'VND-CORRUPT-99',
            'location_name' => 'Corrupted Floor 13',
            'status' => 'ACTIVE',
            'current_stock' => 150, // exceeds max_capacity 100
            'critical_threshold' => 10,
            'max_capacity' => 100,
            'total_sales_count' => 10,
            'total_sales_idr' => 999_999_999, // mismatch with zero transactions
        ]);

        return 'Smart vending audit FAILED';
    }
}
