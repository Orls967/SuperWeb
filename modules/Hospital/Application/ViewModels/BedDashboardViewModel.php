<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\ViewModels;

use Modules\Hospital\Domain\Models\HospitalBed;

final class BedDashboardViewModel
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $beds = HospitalBed::all();
        $total = $beds->count();
        $available = $beds->where('status', 'AVAILABLE')->count();
        $occupied = $beds->where('status', 'OCCUPIED')->count();
        $cleaning = $beds->where('status', 'CLEANING')->count();
        $reserved = $beds->where('status', 'RESERVED')->count();
        $maintenance = $beds->where('status', 'MAINTENANCE')->count();
        $isolated = $beds->where('status', 'ISOLATED_HOLD')->count();

        $bor = $total > 0 ? round(($occupied / $total) * 100, 2) : 0.0;

        $bedsByClass = $beds->groupBy('class')->map(fn ($group) => [
            'total' => $group->count(),
            'available' => $group->where('status', 'AVAILABLE')->count(),
            'occupied' => $group->where('status', 'OCCUPIED')->count(),
        ])->toArray();

        return [
            'summary' => [
                'total_beds' => $total,
                'available_beds' => $available,
                'occupied_beds' => $occupied,
                'cleaning_beds' => $cleaning,
                'reserved_beds' => $reserved,
                'maintenance_beds' => $maintenance,
                'isolated_beds' => $isolated,
                'bor_percentage' => $bor,
            ],
            'classes' => $bedsByClass,
            'beds' => $beds->map(fn ($b) => [
                'id' => $b->id,
                'bed_code' => $b->bed_code,
                'room_number' => $b->room_number,
                'class' => $b->class,
                'status' => $b->status,
                'department' => $b->department,
                'floor' => $b->floor,
                'wing' => $b->wing,
                'updated_at' => $b->updated_at?->toIso8601String(),
            ])->toArray(),
        ];
    }
}
