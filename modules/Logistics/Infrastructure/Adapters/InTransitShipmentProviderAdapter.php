<?php

declare(strict_types=1);

namespace Modules\Logistics\Infrastructure\Adapters;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Contracts\ClockInterface;
use Modules\Logistics\Contracts\InTransitShipmentProvider;

/**
 * Implementasi aditif InTransitShipmentProvider untuk integrasi WMS <-> Logistics.
 */
final class InTransitShipmentProviderAdapter implements InTransitShipmentProvider
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {}

    public function getInTransitForWarehouse(string $warehouseCode): array
    {
        if (! Schema::hasTable('lgx_shipments')) {
            return [];
        }

        $now = $this->clock->now();
        $rows = DB::table('lgx_shipments')
            ->whereIn('status', ['in_transit', 'dispatched', 'picked_up'])
            ->where(function ($q) use ($warehouseCode): void {
                $q->where('origin_code', $warehouseCode)->orWhere('destination_code', $warehouseCode);
            })
            ->select('id', 'tracking_number', 'origin_code', 'destination_code', 'status', 'declared_value_idr', 'updated_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $updated = CarbonImmutable::parse((string) $row->updated_at);
            $out[] = [
                'shipment_id' => (int) $row->id,
                'tracking_number' => (string) $row->tracking_number,
                'origin_code' => (string) ($row->origin_code ?? ''),
                'destination_code' => (string) ($row->destination_code ?? ''),
                'status' => (string) $row->status,
                'declared_value_idr' => (int) ($row->declared_value_idr ?? 0),
                'hours_in_transit' => (int) $updated->diffInHours($now),
            ];
        }

        return $out;
    }

    public function getOverdueInTransitShipments(int $maxHours = 72): array
    {
        if (! Schema::hasTable('lgx_shipments')) {
            return [];
        }

        $now = $this->clock->now();
        $cutoff = $now->subHours($maxHours);
        $rows = DB::table('lgx_shipments')
            ->whereIn('status', ['in_transit', 'dispatched', 'picked_up'])
            ->where('updated_at', '<=', $cutoff)
            ->select('id', 'tracking_number', 'origin_code', 'destination_code', 'updated_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $updated = CarbonImmutable::parse((string) $row->updated_at);
            $out[] = [
                'shipment_id' => (int) $row->id,
                'tracking_number' => (string) $row->tracking_number,
                'origin_code' => (string) ($row->origin_code ?? ''),
                'destination_code' => (string) ($row->destination_code ?? ''),
                'hours_in_transit' => (int) $updated->diffInHours($now),
            ];
        }

        return $out;
    }
}
