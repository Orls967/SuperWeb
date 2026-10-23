<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Illuminate\Support\Str;
use Modules\Ret\Domain\Models\RetInventoryItem;

final class RetAuditCorruptionFixture implements CorruptionFixture
{
    public function command(): string
    {
        return 'ret:audit';
    }

    public function corrupt(): string
    {
        RetInventoryItem::create([
            'id' => (string) Str::uuid(),
            'sku' => 'SKU-CORRUPT-99',
            'product_name' => 'Corrupted Inventory Item',
            'stock_available' => -50,
            'stock_reserved' => 0,
            'map_price_minor' => 100_000,
        ]);

        return 'Retail audit FAILED';
    }
}
