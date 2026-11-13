<?php

declare(strict_types=1);

namespace Modules\ControlTower\Application\Services;

use Modules\ControlTower\Domain\Models\DisruptionAlert;
use Modules\ControlTower\Domain\Models\EchelonStock;
use Modules\ControlTower\Domain\Models\OrderPromise;
use Modules\ControlTower\Domain\Models\SopCycle;
use Modules\ControlTower\Domain\StateMachines\SopCycleStateMachine;

final class ControlTowerAuditReportService
{
    /**
     * @return array{
     *     status: string,
     *     checked_at_counts: array<string, int>,
     *     violations: list<string>
     * }
     */
    public function buildDetailedReport(): array
    {
        $violations = [];

        $negativeStock = EchelonStock::query()
            ->where(function ($q): void {
                $q->where('on_hand_qty', '<', 0)
                    ->orWhere('in_transit_qty', '<', 0)
                    ->orWhere('reserved_qty', '<', 0);
            })
            ->count();
        if ($negativeStock > 0) {
            $violations[] = "Ditemukan {$negativeStock} baris echelon stock bernilai negatif.";
        }

        $overReserved = EchelonStock::query()
            ->whereRaw('reserved_qty > (on_hand_qty + in_transit_qty)')
            ->count();
        if ($overReserved > 0) {
            $violations[] = "Ditemukan {$overReserved} baris stok terreservasi melebihi pasokan fisik+transit.";
        }

        $invalidPromise = OrderPromise::query()
            ->whereColumn('atp_confirmed_qty', '>', 'requested_qty')
            ->count();
        if ($invalidPromise > 0) {
            $violations[] = "Ditemukan {$invalidPromise} janji pesanan dengan ATP melebihi permintaan.";
        }

        $invalidSop = SopCycle::query()
            ->whereNotIn('status', SopCycleStateMachine::states())
            ->count();
        if ($invalidSop > 0) {
            $violations[] = "Ditemukan {$invalidSop} siklus S&OP dengan status tidak sah.";
        }

        return [
            'status' => $violations === [] ? 'CLEAN' : 'VIOLATION',
            'checked_at_counts' => [
                'echelons' => EchelonStock::query()->count(),
                'promises' => OrderPromise::query()->count(),
                'alerts' => DisruptionAlert::query()->count(),
                'sop_cycles' => SopCycle::query()->count(),
            ],
            'violations' => $violations,
        ];
    }
}
