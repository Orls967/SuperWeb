<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * SupplyChainTradeMaterialFlowsService (Fase 381)
 *
 * Implements:
 *  - 381.1 Unified shipment and material lot identity tracking
 *  - 381.2 Transit quality inspection with quarantine hold
 *  - 381.4 Tests: Trade hold gates consistent; return ownership valid; trade:audit clean
 *  - 381.5 Edge case: Material failing quality in-transit triggers quarantine + trade hold, never proceeds to consignee
 *  - 381.6 Risk: Reverse flow without contract or manifest strictly blocked from pickup
 */
class SupplyChainTradeMaterialFlowsService
{
    /**
     * Inspect shipment quality in-transit (381.2, 381.4, 381.5 Edge Case).
     */
    public function inspectTransitShipment(
        string $shipmentCode,
        string $lotIdentifier,
        bool $qualityPassed
    ): object {
        $sCode = strtoupper($shipmentCode);
        $lId = strtoupper($lotIdentifier);

        // Edge case 381.5: Material failing quality in-transit must be quarantined, delivery prohibited
        $quarantineHold = ! $qualityPassed;
        $deliveryPermitted = $qualityPassed;

        if (! $qualityPassed) {
            DB::table('global_supply_chain_shipment_tracks')->insert([
                'shipment_code' => $sCode,
                'lot_identifier' => $lId,
                'transit_quality_passed' => false,
                'quarantine_hold_active' => true,
                'delivery_to_consignee_permitted' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            throw new InvalidArgumentException("Transit quality failure: Shipment '{$shipmentCode}' failed quality inspection and is placed under quarantine trade hold (381.5).");
        }

        $id = DB::table('global_supply_chain_shipment_tracks')->insertGetId([
            'shipment_code' => $sCode,
            'lot_identifier' => $lId,
            'transit_quality_passed' => true,
            'quarantine_hold_active' => false,
            'delivery_to_consignee_permitted' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_supply_chain_shipment_tracks')->find($id);
    }

    /**
     * Authorize reverse flow pickup requiring contract & manifest verification (381.3, 381.4, 381.6 Risk).
     */
    public function authorizeReverseFlowPickup(
        string $reverseManifestCode,
        string $lotIdentifier,
        bool $hasVerifiedContract,
        bool $hasPickupManifest
    ): object {
        $rCode = strtoupper($reverseManifestCode);
        $lId = strtoupper($lotIdentifier);

        // Risk gate 381.6: Reverse flow without ownership contract & manifest strictly blocked
        if (! $hasVerifiedContract || ! $hasPickupManifest) {
            throw new InvalidArgumentException("Reverse logistics security violation: Mandatory contract and pickup manifest required before pickup authorization (381.6).");
        }

        $id = DB::table('global_supply_chain_reverse_manifests')->insertGetId([
            'reverse_manifest_code' => $rCode,
            'lot_identifier' => $lId,
            'has_verified_contract' => true,
            'has_pickup_manifest' => true,
            'pickup_authorized' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('global_supply_chain_reverse_manifests')->find($id);
    }

    /**
     * Global Trade & Supply Chain Audit (`trade:audit`) (381.4, 381.8).
     */
    public function audit(): array
    {
        // Discrepancy 1: Failed transit shipments permitted to deliver
        $deliveredFailedShipments = DB::table('global_supply_chain_shipment_tracks')
            ->where('transit_quality_passed', false)
            ->where('delivery_to_consignee_permitted', true)
            ->count();

        // Discrepancy 2: Reverse pickups authorized without contract or manifest
        $unauthorizedPickups = DB::table('global_supply_chain_reverse_manifests')
            ->where('pickup_authorized', true)
            ->where(function ($query) {
                $query->where('has_verified_contract', false)
                    ->orWhere('has_pickup_manifest', false);
            })
            ->count();

        $discrepancies = $deliveredFailedShipments + $unauthorizedPickups;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_shipments' => DB::table('global_supply_chain_shipment_tracks')->count(),
            'total_reverse_manifests' => DB::table('global_supply_chain_reverse_manifests')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
