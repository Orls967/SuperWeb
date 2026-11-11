<?php

declare(strict_types=1);

namespace Modules\Wms\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Wms\Domain\Models\Warehouse;

class WarehouseResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'warehouses';
    }

    public function moduleName(): string
    {
        return 'Wms';
    }

    public function title(): string
    {
        return 'Warehouses';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'is_active' => 'exact',
            'city' => 'like',
            'code' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['code', 'name', 'city'];
    }

    public function defaultSortColumn(): string
    {
        return 'code';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Warehouse UUID'],
            'code' => ['type' => 'string', 'description' => 'Warehouse code'],
            'name' => ['type' => 'string', 'description' => 'Warehouse display name'],
            'city' => ['type' => 'string', 'description' => 'City location'],
            'is_active' => ['type' => 'boolean', 'description' => 'Whether warehouse is active'],
        ];
    }

    public function declaredActions(): array
    {
        return [
            'toggle-status' => [
                'description' => 'Toggle active status of warehouse',
                'ability' => 'wms:warehouses:manage',
                'required_fields' => [],
            ],
        ];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return Warehouse::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var Warehouse $record */
        return [
            'id' => (string) $record->id,
            'code' => (string) $record->code,
            'name' => (string) $record->name,
            'city' => (string) $record->city,
            'is_active' => (bool) $record->is_active,
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        if ($action !== 'toggle-status') {
            throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada warehouses.");
        }

        $id = (string) ($payload['id'] ?? '');
        $warehouse = Warehouse::findOrFail($id);
        $warehouse->update(['is_active' => ! $warehouse->is_active]);

        return [
            'status' => 200,
            'data' => $this->mapRecord($warehouse->fresh()),
        ];
    }
}
