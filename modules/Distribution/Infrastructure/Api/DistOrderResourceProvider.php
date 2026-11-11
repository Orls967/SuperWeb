<?php

declare(strict_types=1);

namespace Modules\Distribution\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Distribution\Domain\Models\DistOrder;

class DistOrderResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'distribution-orders';
    }

    public function moduleName(): string
    {
        return 'Distribution';
    }

    public function title(): string
    {
        return 'Distribution Orders';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'number' => 'like',
            'distributor_id' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['number', 'created_at', 'total_idr'];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Distribution order UUID'],
            'number' => ['type' => 'string', 'description' => 'Distribution order number'],
            'status' => ['type' => 'string', 'description' => 'Order status'],
            'total_idr' => ['type' => 'integer', 'description' => 'Total order amount in IDR minor units'],
            'currency' => ['type' => 'string', 'description' => 'Currency code'],
        ];
    }

    public function declaredActions(): array
    {
        return [];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return DistOrder::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var DistOrder $record */
        return [
            'id' => (string) $record->id,
            'number' => (string) $record->number,
            'status' => (string) $record->status,
            'total_idr' => (int) $record->total_idr,
            'currency' => (string) ($record->currency ?? 'IDR'),
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada distribution-orders.");
    }
}
