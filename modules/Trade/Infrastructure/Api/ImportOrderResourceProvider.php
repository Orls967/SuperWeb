<?php

declare(strict_types=1);

namespace Modules\Trade\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Trade\Domain\Models\ImportOrder;

class ImportOrderResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'import-orders';
    }

    public function moduleName(): string
    {
        return 'Trade';
    }

    public function title(): string
    {
        return 'Import Orders';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'order_number' => 'like',
            'origin_country_code' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['order_number', 'created_at', 'total_landed_cost_idr'];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Import order UUID'],
            'order_number' => ['type' => 'string', 'description' => 'Trade import order number'],
            'supplier_name' => ['type' => 'string', 'description' => 'Overseas supplier name'],
            'status' => ['type' => 'string', 'description' => 'Import lifecycle status'],
            'total_landed_cost_idr' => ['type' => 'integer', 'description' => 'Total landed cost in IDR minor units'],
        ];
    }

    public function declaredActions(): array
    {
        return [];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return ImportOrder::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var ImportOrder $record */
        return [
            'id' => (string) $record->id,
            'order_number' => (string) $record->order_number,
            'supplier_name' => (string) $record->supplier_name,
            'status' => (string) $record->status,
            'total_landed_cost_idr' => (int) $record->total_landed_cost_idr,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada import-orders.");
    }
}

