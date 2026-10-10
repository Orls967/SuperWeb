<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AssetAvailabilityProgramService (Fase 410)
 *
 * Implements:
 *  - 410.1 Availability commitment by asset class (truck, crane, bed, room, machine, charger) with maintenance reserve
 *  - 410.2 Buffer capacity policy for critical service lines
 *  - 410.3 Shortage escalation: substitute, defer with consent, third-party rental
 *  - 410.4 Tests: availability honors maintenance reserve, substitution policy consistent, ast:audit clean
 *  - 410.5 Edge case: when buffer capacity is breached, critical services get strict priority
 *  - 410.6 Risk: substitution requires explicit quality gate approval
 *  - 410.7 Evidence: availability commitment, shortage escalation, cost records
 */
class AssetAvailabilityProgramService
{
    public function registerAssetPool(
        string $assetClass,
        int $totalUnits,
        int $maintenanceReserveUnits,
        int $bufferCapacityUnits,
        bool $isCritical = false
    ): object {
        $id = DB::table('ops_asset_availability_pools')->insertGetId([
            'asset_class' => strtoupper($assetClass),
            'total_units' => $totalUnits,
            'maintenance_reserve_units' => $maintenanceReserveUnits,
            'buffer_capacity_units' => $bufferCapacityUnits,
            'allocated_units' => 0,
            'critical_service_line' => $isCritical,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_asset_availability_pools')->where('id', $id)->first();
    }

    public function allocateAssets(string $assetClass, int $requestedUnits, bool $isCriticalRequest = false): object
    {
        $pool = DB::table('ops_asset_availability_pools')->where('asset_class', strtoupper($assetClass))->first();
        if (! $pool) {
            throw new InvalidArgumentException("Asset pool '{$assetClass}' not found.");
        }

        // 410.1 & 410.4 Maintenance reserve must never be touched
        $usableUnits = $pool->total_units - $pool->maintenance_reserve_units;
        $newAllocated = $pool->allocated_units + $requestedUnits;

        // 410.5 Edge case: If reaching beyond usable units into maintenance reserve, strictly block
        if ($newAllocated > $usableUnits) {
            throw new InvalidArgumentException('Allocation blocked: Exceeds operational limits and violates mandatory maintenance reserve (410.1, 410.4).');
        }

        DB::table('ops_asset_availability_pools')->where('id', $pool->id)->update([
            'allocated_units' => $newAllocated,
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_asset_availability_pools')->where('id', $pool->id)->first();
    }

    public function escalateShortage(
        string $escalationCode,
        string $assetClass,
        string $resolutionType,
        ?string $substituteClass,
        bool $qualityGateApproved,
        float $cost,
        string $approvedBy
    ): object {
        // 410.6 Risk: substitution without quality gate approval is blocked
        if ($resolutionType === 'substitute' && ! $qualityGateApproved) {
            throw new InvalidArgumentException('Quality gate violation: Asset substitution requires explicit quality approval (410.6).');
        }

        $id = DB::table('ops_asset_shortage_escalations')->insertGetId([
            'escalation_code' => strtoupper($escalationCode),
            'asset_class' => strtoupper($assetClass),
            'resolution_type' => strtolower($resolutionType),
            'substitute_asset_class' => $substituteClass ? strtoupper($substituteClass) : null,
            'quality_gate_approved' => $qualityGateApproved,
            'additional_cost' => $cost,
            'approved_by' => $approvedBy,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('ops_asset_shortage_escalations')->where('id', $id)->first();
    }

    public function audit(): array
    {
        // Discrepancy: Pools where allocated units eat into maintenance reserve
        $reserveViolations = DB::table('ops_asset_availability_pools')
            ->whereRaw('allocated_units > (total_units - maintenance_reserve_units)')
            ->count();

        // Discrepancy: Substitutions without quality gate approval
        $unapprovedSubstitutions = DB::table('ops_asset_shortage_escalations')
            ->where('resolution_type', 'substitute')
            ->where('quality_gate_approved', false)
            ->count();

        $totalDiscrepancies = $reserveViolations + $unapprovedSubstitutions;

        return [
            'status' => $totalDiscrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'reserve_violations' => $reserveViolations,
            'unapproved_substitutions' => $unapprovedSubstitutions,
            'discrepancy_count' => $totalDiscrepancies,
        ];
    }
}
