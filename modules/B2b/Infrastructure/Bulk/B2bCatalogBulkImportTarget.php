<?php

declare(strict_types=1);

namespace Modules\B2b\Infrastructure\Bulk;

use Modules\B2b\Domain\Models\WholesaleCatalog;
use Modules\Core\Contracts\BulkImportTarget;

class B2bCatalogBulkImportTarget implements BulkImportTarget
{
    public function targetKey(): string
    {
        return 'b2b-catalog';
    }

    public function moduleName(): string
    {
        return 'B2b';
    }

    public function label(): string
    {
        return 'B2B Wholesale Catalog';
    }

    public function requiredColumns(): array
    {
        return ['sku', 'product_name', 'category', 'base_price_idr', 'min_order_qty', 'available_stock'];
    }

    public function validateRow(array $row, int $lineNumber): array
    {
        $sku = trim($row['sku'] ?? '');
        $name = trim($row['product_name'] ?? '');
        $price = (int) ($row['base_price_idr'] ?? 0);
        $minQty = (int) ($row['min_order_qty'] ?? 1);
        $stock = (int) ($row['available_stock'] ?? 0);

        if ($sku === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: SKU wajib diisi.", 'normalized' => []];
        }

        if ($name === '') {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Nama produk wajib diisi.", 'normalized' => []];
        }

        if ($price <= 0) {
            return ['valid' => false, 'reason' => "Baris {$lineNumber}: Harga dasar wajib bernilai positif.", 'normalized' => []];
        }

        return [
            'valid' => true,
            'reason' => null,
            'normalized' => [
                'sku' => $sku,
                'vendor_id' => ! empty($row['vendor_id']) ? (string) $row['vendor_id'] : 'vnd_default',
                'product_name' => $name,
                'category' => trim($row['category'] ?? 'General'),
                'base_price_idr' => $price,
                'min_order_qty' => max(1, $minQty),
                'available_stock' => max(0, $stock),
                'status' => 'active',
            ],
        ];
    }

    public function applyBatch(array $normalizedRows, string $idempotencyKey, string $tenantId): int
    {
        $count = 0;
        foreach ($normalizedRows as $row) {
            if (empty($row['vendor_id']) || $row['vendor_id'] === 'vnd_default') {
                $row['vendor_id'] = $tenantId ?: 'vnd_default';
            }
            WholesaleCatalog::updateOrCreate(
                ['sku' => $row['sku']],
                $row
            );
            $count++;
        }

        return $count;
    }

    public function exportRows(int $maxRows = 50000): iterable
    {
        $catalogs = WholesaleCatalog::limit($maxRows)->get();
        foreach ($catalogs as $cat) {
            yield [
                'sku' => $cat->sku,
                'product_name' => $cat->product_name,
                'category' => $cat->category,
                'base_price_idr' => $cat->base_price_idr,
                'min_order_qty' => $cat->min_order_qty,
                'available_stock' => $cat->available_stock,
            ];
        }
    }
}
