<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Services;

use Modules\Mining\Domain\Enums\HaulCycleStatus;
use Modules\Mining\Domain\Models\MiningEquipment;
use Modules\Mining\Domain\Models\MiningHaulCycle;

/**
 * OEE alat angkut (D93-3) dalam bps dari fakta siklus:
 *  - ketersediaan  = siklus tidak aborted / seluruh siklus truk,
 *  - kinerja       = Σ payload aktual / Σ payload target siklus selesai,
 *  - kualitas      = siklus selesai dengan payload 95–105% target / siklus selesai.
 * OEE = A × P × Q / 10^8 (bps). Urutan: OEE turun, kode alat naik (tie-break eksplisit).
 */
final class FleetOeeRankingService
{
    /**
     * @return list<array{rank: int, equipment_id: string, code: string, availability_bps: int, performance_bps: int, quality_bps: int, oee_bps: int}>
     */
    public function rank(?string $siteId = null): array
    {
        $trucks = MiningEquipment::query()
            ->where('type', 'HAUL_TRUCK')
            ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('equipment_code')
            ->get();

        $cycles = MiningHaulCycle::query()
            ->whereIn('truck_id', $trucks->pluck('id'))
            ->get()
            ->groupBy('truck_id');

        $rows = [];
        foreach ($trucks as $truck) {
            $all = $cycles->get($truck->id, collect());
            $done = $all->filter(fn (MiningHaulCycle $c) => $c->status === HaulCycleStatus::COMPLETED);

            $availability = $all->count() > 0
                ? intdiv(($all->count() - $all->where('status', HaulCycleStatus::ABORTED)->count()) * 10000, $all->count())
                : 0;
            $target = (int) $done->sum('target_payload_kg');
            $performance = $target > 0 ? min(10000, intdiv((int) $done->sum('actual_payload_kg') * 10000, $target)) : 0;
            $inBand = $done->filter(fn (MiningHaulCycle $c) => $c->target_payload_kg > 0
                && $c->actual_payload_kg * 100 >= $c->target_payload_kg * 95
                && $c->actual_payload_kg * 100 <= $c->target_payload_kg * 105)->count();
            $quality = $done->count() > 0 ? intdiv($inBand * 10000, $done->count()) : 0;

            $rows[] = [
                'rank' => 0,
                'equipment_id' => $truck->id,
                'code' => $truck->equipment_code,
                'availability_bps' => $availability,
                'performance_bps' => $performance,
                'quality_bps' => $quality,
                'oee_bps' => intdiv($availability * $performance * $quality, 100000000),
            ];
        }

        usort($rows, fn ($a, $b) => [$b['oee_bps'], $a['code']] <=> [$a['oee_bps'], $b['code']]);
        foreach ($rows as $i => &$row) {
            $row['rank'] = $i + 1;
        }

        return $rows;
    }
}
