<?php

declare(strict_types=1);

namespace Modules\Procurement\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Procurement\Domain\Models\PurchaseOrder;

class PurchaseOrderResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'purchase-orders';
    }

    public function moduleName(): string
    {
        return 'Procurement';
    }

    public function title(): string
    {
        return 'Purchase Orders';
    }

    public function deprecatedV1Path(): ?string
    {
        return null;
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'supplier_id' => 'exact',
            'number' => 'like',
        ];
    }

    public function declaredSorts(): array
    {
        return ['number', 'created_at', 'total_amount'];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'string', 'description' => 'Purchase order UUID'],
            'number' => ['type' => 'string', 'description' => 'Human-readable PO number'],
            'status' => ['type' => 'string', 'description' => 'PO lifecycle status'],
            'total_amount' => ['type' => 'integer', 'description' => 'Total amount in integer minor units (cents)'],
            'currency' => ['type' => 'string', 'description' => 'ISO 4217 currency code'],
        ];
    }

    public function declaredActions(): array
    {
        return [
            'cancel' => [
                'description' => 'Cancel purchase order',
                'ability' => 'procurement:orders:cancel',
                'required_fields' => ['reason'],
            ],
            'acknowledge' => [
                'description' => 'Acknowledge purchase order',
                'ability' => 'resource:action',
                'required_fields' => ['po_id'],
            ],
        ];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return PurchaseOrder::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var PurchaseOrder $record */
        return [
            'id' => (string) $record->id,
            'number' => (string) $record->number,
            'status' => (string) $record->status,
            'total_amount' => (int) $record->total_amount,
            'currency' => (string) $record->currency,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        if ($action === 'acknowledge') {
            $id = (string) ($payload['po_id'] ?? $payload['id'] ?? '');
            $po = PurchaseOrder::findOrFail($id);
            return [
                'status' => 200,
                'data' => [
                    'id' => (string) $po->id,
                    'status' => 'acknowledged',
                    'note' => (string) ($payload['note'] ?? ''),
                ],
            ];
        }

        if ($action !== 'cancel') {
            throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada purchase-orders.");
        }

        $id = (string) ($payload['id'] ?? '');
        $po = PurchaseOrder::findOrFail($id);
        $po->update(['status' => 'cancelled']);

        return [
            'status' => 200,
            'data' => $this->mapRecord($po->fresh()),
        ];
    }
}
