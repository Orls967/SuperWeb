<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SupplyChainNetworkService (Fase 215)
 *
 * Implements:
 *  - 215.2 Multi-echelon stock deployment validating allocation cannot exceed available supply
 *  - 215.3 Yard & dock scheduling with conflict detection preventing double booking
 */
class SupplyChainNetworkService
{
    /**
     * Allocate multi-echelon stock ensuring strict supply conservation.
     */
    public function allocateStock(string $sku, string $hub, int $supply, int $quantity): object
    {
        if ($quantity > $supply) {
            throw new \InvalidArgumentException("Allocation error: Requested quantity {$quantity} exceeds available stock supply {$supply}.");
        }

        $code = 'ALC-'.strtoupper(Str::random(8));

        $id = DB::table('ops_sc_echelon_allocations')->insertGetId([
            'allocation_code' => $code,
            'sku_code' => strtoupper($sku),
            'hub_code' => strtoupper($hub),
            'available_stock_supply' => $supply,
            'allocated_quantity' => $quantity,
            'allocation_valid' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_sc_echelon_allocations')->find($id);
    }

    /**
     * Schedule yard dock appointment with overlap conflict prevention.
     */
    public function scheduleDockAppointment(string $facility, string $dockDoor, string $timeSlot, string $carrier): object
    {
        $existing = DB::table('ops_sc_dock_appointments')
            ->where('facility_code', strtoupper($facility))
            ->where('dock_door_code', strtoupper($dockDoor))
            ->where('slot_time_window', $timeSlot)
            ->where('status', 'SCHEDULED')
            ->first();

        if ($existing) {
            throw new \RuntimeException("Dock scheduling conflict: Door {$dockDoor} at facility {$facility} is already booked for slot {$timeSlot}.");
        }

        $code = 'DOCK-'.strtoupper(Str::random(8));

        $id = DB::table('ops_sc_dock_appointments')->insertGetId([
            'appointment_code' => $code,
            'facility_code' => strtoupper($facility),
            'dock_door_code' => strtoupper($dockDoor),
            'slot_time_window' => $timeSlot,
            'carrier_code' => strtoupper($carrier),
            'status' => 'SCHEDULED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_sc_dock_appointments')->find($id);
    }

    /**
     * Quality audit gate (`wms:audit`).
     */
    public function audit(): array
    {
        $excessAllocations = DB::table('ops_sc_echelon_allocations')
            ->whereRaw('allocated_quantity > available_stock_supply')
            ->count();

        return [
            'status' => $excessAllocations === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_allocations' => DB::table('ops_sc_echelon_allocations')->count(),
            'total_dock_appointments' => DB::table('ops_sc_dock_appointments')->count(),
            'discrepancy_count' => $excessAllocations,
        ];
    }
}
