<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * PortOperationsService (Fase 179 — Lini 27)
 *
 * Implements:
 *  - 179.1 Berth window reservation with overlap conflict rejection
 *  - 179.3 Yard block capacity limit enforcement and reefer excursion detection
 *  - 179.4 Terminal billing & port dues invoicing
 */
class PortOperationsService
{
    /**
     * Reserve berth window. Rejects overlapping reservation for same berth.
     */
    public function reserveBerth(string $berthNum, string $vesselName, Carbon $start, Carbon $end): object
    {
        // Check for time overlap on same berth
        $overlap = DB::table('prt_berth_reservations')
            ->where('berth_number', $berthNum)
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('window_start', [$start, $end])
                    ->orWhereBetween('window_end', [$start, $end])
                    ->orWhere(function ($q) use ($start, $end) {
                        $q->where('window_start', '<=', $start)
                            ->where('window_end', '>=', $end);
                    });
            })
            ->exists();

        if ($overlap) {
            throw new \RuntimeException("Berth reservation conflict: Berth {$berthNum} is already booked during this time window.");
        }

        $code = 'RES-PRT-'.strtoupper(Str::random(8));

        $id = DB::table('prt_berth_reservations')->insertGetId([
            'reservation_code' => $code,
            'berth_number' => $berthNum,
            'vessel_name' => $vesselName,
            'window_start' => $start,
            'window_end' => $end,
            'has_overlap_conflict' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('prt_berth_reservations')->find($id);
    }

    /**
     * Allocate container to yard block.
     * Enforces yard block capacity and checks reefer temperature excursion (safe temp <= -15°C or <= 4°C).
     */
    public function allocateYardSlot(string $blockId, string $containerNum, int $maxCapacity, bool $isReefer = false, ?float $reeferTempC = null): object
    {
        $currentOccupancy = DB::table('prt_yard_allocations')
            ->where('yard_block_id', $blockId)
            ->count();

        if ($currentOccupancy >= $maxCapacity) {
            throw new \RuntimeException("Yard capacity exceeded: Block {$blockId} has reached maximum capacity of {$maxCapacity} containers.");
        }

        $excursion = false;
        if ($isReefer && $reeferTempC !== null) {
            // Excursion if reefer temperature rises above 4.0°C
            $excursion = ($reeferTempC > 4.0);
        }

        $code = 'YRD-PRT-'.strtoupper(Str::random(8));

        $id = DB::table('prt_yard_allocations')->insertGetId([
            'allocation_code' => $code,
            'yard_block_id' => $blockId,
            'container_number' => $containerNum,
            'max_block_capacity' => $maxCapacity,
            'is_reefer' => $isReefer,
            'reefer_temp_c' => $reeferTempC,
            'reefer_temp_excursion' => $excursion,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('prt_yard_allocations')->find($id);
    }

    /**
     * Generate terminal dues and crane moves invoice.
     */
    public function generateTerminalInvoice(string $resCode, int $craneMoves, float $ratePerMoveIdr, float $portDuesIdr): object
    {
        $movesAmount = round($craneMoves * $ratePerMoveIdr, 2);
        $total = round($movesAmount + $portDuesIdr, 2);

        $invCode = 'INV-PRT-'.strtoupper(Str::random(8));

        $id = DB::table('prt_terminal_invoices')->insertGetId([
            'invoice_code' => $invCode,
            'reservation_code' => $resCode,
            'container_moves' => $craneMoves,
            'rate_per_move_idr' => $ratePerMoveIdr,
            'port_dues_idr' => $portDuesIdr,
            'total_amount_idr' => $total,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('prt_terminal_invoices')->find($id);
    }

    /**
     * Quality audit gate (`port:audit`).
     */
    public function audit(): array
    {
        $overlapCount = DB::table('prt_berth_reservations')
            ->where('has_overlap_conflict', true)
            ->count();

        return [
            'status' => $overlapCount === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_berth_reservations' => DB::table('prt_berth_reservations')->count(),
            'total_yard_allocations' => DB::table('prt_yard_allocations')->count(),
            'total_invoices' => DB::table('prt_terminal_invoices')->count(),
            'discrepancy_count' => $overlapCount,
        ];
    }
}
