<?php

declare(strict_types=1);

namespace Modules\B2b\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\B2b\Domain\Models\WholesaleCatalog;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;

class WholesaleCatalogResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'wholesale-catalogs';
    }

    public function moduleName(): string
    {
        return 'B2b';
    }

    public function title(): string
    {
        return 'B2B Wholesale Catalogs';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'category' => 'exact',
            'sku' => 'like',
        ];
    }

    public function declaredSorts(): array
    {
        return ['sku', 'product_name', 'base_price_idr', 'created_at'];
    }

    public function defaultSortColumn(): string
    {
        return 'sku';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Wholesale item UUID'],
            'sku' => ['type' => 'string', 'description' => 'Stock Keeping Unit'],
            'product_name' => ['type' => 'string', 'description' => 'Catalog item title'],
            'category' => ['type' => 'string', 'description' => 'Product category'],
            'base_price_idr' => ['type' => 'integer', 'description' => 'Base price in IDR minor units'],
            'min_order_qty' => ['type' => 'integer', 'description' => 'Minimum order quantity'],
            'available_stock' => ['type' => 'integer', 'description' => 'Available wholesale stock units'],
            'status' => ['type' => 'string', 'description' => 'Availability status'],
        ];
    }

    public function declaredActions(): array
    {
        return [];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return WholesaleCatalog::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var WholesaleCatalog $record */
        return [
            'id' => (string) $record->id,
            'sku' => (string) $record->sku,
            'product_name' => (string) $record->product_name,
            'category' => (string) $record->category,
            'base_price_idr' => (int) $record->base_price_idr,
            'min_order_qty' => (int) $record->min_order_qty,
            'available_stock' => (int) $record->available_stock,
            'status' => (string) $record->status,
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada wholesale-catalogs.");
    }
}

