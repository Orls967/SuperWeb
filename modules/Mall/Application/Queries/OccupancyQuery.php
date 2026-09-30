<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Queries;

use Carbon\Carbon;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\UnitStatus;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Unit;

class OccupancyQuery
{
    /**
     * @return array{
     *     total_gla_sqm: float,
     *     leased_gla_sqm: float,
     *     available_gla_sqm: float,
     *     occupancy_rate_percent: float,
     *     total_units: int,
     *     leased_units: int,
     *     available_units: int,
     *     reserved_units: int,
     *     maintenance_units: int,
     *     floor_breakdown: array<string, array{floor: string, total_units: int, leased_units: int, total_area: float, leased_area: float, occupancy_percent: float}>,
     *     expiring_soon_leases: array<int, Lease>
     * }
     */
    public function execute(?int $propertyId = null): array
    {
        $propertyQuery = Property::where('is_active', true);
        if ($propertyId) {
            $propertyQuery->where('id', $propertyId);
        }
        $properties = $propertyQuery->get();
        $targetPropertyIds = $properties->pluck('id')->all();

        $units = Unit::with(['activeLease.tenant', 'zone'])
            ->whereIn('property_id', $targetPropertyIds)
            ->get();

        $totalGla = (float) $units->sum('area_sqm');
        $leasedUnits = $units->filter(fn ($u) => $u->status === UnitStatus::LEASED);
        $leasedGla = (float) $leasedUnits->sum('area_sqm');
        $availableGla = $totalGla - $leasedGla;

        $occupancyPercent = $totalGla > 0 ? round(($leasedGla / $totalGla) * 100, 2) : 0.0;

        // Breakdown per lantai
        $floors = $units->groupBy('floor');
        $floorBreakdown = [];

        foreach ($floors as $floorName => $floorUnits) {
            $fTotalArea = (float) $floorUnits->sum('area_sqm');
            $fLeasedUnits = $floorUnits->filter(fn ($u) => $u->status === UnitStatus::LEASED);
            $fLeasedArea = (float) $fLeasedUnits->sum('area_sqm');
            $fOccPercent = $fTotalArea > 0 ? round(($fLeasedArea / $fTotalArea) * 100, 2) : 0.0;

            $floorBreakdown[$floorName] = [
                'floor' => (string) $floorName,
                'total_units' => $floorUnits->count(),
                'leased_units' => $fLeasedUnits->count(),
                'total_area' => round($fTotalArea, 2),
                'leased_area' => round($fLeasedArea, 2),
                'occupancy_percent' => $fOccPercent,
            ];
        }

        // Kontrak yang berakhir dalam 90 hari
        $in90Days = Carbon::now()->addDays(90)->toDateString();
        $expiringLeases = Lease::with(['tenant', 'unit', 'property'])
            ->whereIn('property_id', $targetPropertyIds)
            ->where('status', LeaseStatus::ACTIVE)
            ->whereDate('end_date', '<=', $in90Days)
            ->whereDate('end_date', '>=', Carbon::now()->toDateString())
            ->orderBy('end_date')
            ->get();

        return [
            'total_gla_sqm' => round($totalGla, 2),
            'leased_gla_sqm' => round($leasedGla, 2),
            'available_gla_sqm' => round($availableGla, 2),
            'occupancy_rate_percent' => $occupancyPercent,
            'total_units' => $units->count(),
            'leased_units' => $leasedUnits->count(),
            'available_units' => $units->where('status', UnitStatus::AVAILABLE)->count(),
            'reserved_units' => $units->where('status', UnitStatus::RESERVED)->count(),
            'maintenance_units' => $units->where('status', UnitStatus::MAINTENANCE)->count(),
            'floor_breakdown' => $floorBreakdown,
            'expiring_soon_leases' => $expiringLeases->all(),
        ];
    }
}
