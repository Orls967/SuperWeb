<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Api;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Tenancy\TenantContext;
use Modules\Core\Contracts\ApiResourceProvider;
use Modules\Logistics\Domain\Models\Shipment;

class ShipmentResourceProvider implements ApiResourceProvider
{
    public function resourceKey(): string
    {
        return 'shipments';
    }

    public function moduleName(): string
    {
        return 'Logistics';
    }

    public function title(): string
    {
        return 'Logistics Shipments';
    }

    public function deprecatedV1Path(): ?string
    {
        return '/api/v1/logistics/shipments';
    }

    public function declaredFilters(): array
    {
        return [
            'status' => 'exact',
            'tracking_number' => 'like',
            'shipper_id' => 'exact',
        ];
    }

    public function declaredSorts(): array
    {
        return ['tracking_number', 'created_at', 'status'];
    }

    public function defaultSortColumn(): string
    {
        return 'created_at';
    }

    public function schemaProperties(): array
    {
        return [
            'id' => ['type' => 'integer', 'description' => 'Shipment internal ID'],
            'tracking_number' => ['type' => 'string', 'description' => 'Waybill tracking number'],
            'status' => ['type' => 'string', 'description' => 'Shipment transit status'],
            'declared_value_idr' => ['type' => 'integer', 'description' => 'Declared value in IDR cents'],
            'consignee_name' => ['type' => 'string', 'description' => 'Recipient name'],
            'consignee_phone_masked' => ['type' => 'string', 'description' => 'Masked recipient phone number'],
        ];
    }

    public function declaredActions(): array
    {
        return [
            'update-status' => [
                'description' => 'Update shipment transit status',
                'ability' => 'logistics:shipments:update',
                'required_fields' => ['status'],
            ],
        ];
    }

    public function baseQuery(TenantContext $context): Builder
    {
        return Shipment::query();
    }

    public function mapRecord(Model $record): array
    {
        /** @var Shipment $record */
        $phone = (string) $record->consignee_phone;
        $maskedPhone = strlen($phone) > 4
            ? substr($phone, 0, 3).str_repeat('*', max(0, strlen($phone) - 7)).substr($phone, -4)
            : '***';

        return [
            'id' => (int) $record->id,
            'tracking_number' => (string) $record->tracking_number,
            'status' => (string) ($record->status->value ?? $record->status),
            'declared_value_idr' => (int) $record->declared_value_idr,
            'consignee_name' => (string) $record->consignee_name,
            'consignee_phone_masked' => $maskedPhone,
            'created_at' => $record->created_at?->toIso8601String(),
        ];
    }

    public function executeAction(string $action, array $payload, string $idempotencyKey, TenantContext $context): array
    {
        if ($action !== 'update-status') {
            throw new DomainException("ACTION_UNKNOWN: Aksi [{$action}] tidak didukung pada shipments.");
        }

        $id = (int) ($payload['id'] ?? 0);
        $shipment = Shipment::findOrFail($id);
        $targetStatus = (string) ($payload['status'] ?? '');
        $shipment->update(['status' => $targetStatus]);

        return [
            'status' => 200,
            'data' => $this->mapRecord($shipment->fresh()),
        ];
    }
}
