<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\BarShiftLog;

final class AuditBarShiftStockAction extends BaseAction
{
    /**
     * Audit rekonsiliasi stok botol bar antar-shift.
     *
     * @return array{
     *     shift_log: BarShiftLog,
     *     expected_closing_bottles: int,
     *     actual_closing_bottles: int,
     *     variance_bottles: int,
     *     spillage_bottles_equivalent: int,
     *     status: string
     * }
     */
    public function execute(
        int $shiftLogId,
        int $actualClosingBottles,
        int $supervisorUserId,
        int $standardBottleVolumeMl = 750
    ): array {
        return DB::transaction(function () use ($shiftLogId, $actualClosingBottles, $supervisorUserId, $standardBottleVolumeMl) {
            $shift = BarShiftLog::query()->lockForUpdate()->find($shiftLogId);
            if (! $shift) {
                throw new InvalidArgumentException("Bar shift log ID {$shiftLogId} tidak ditemukan.");
            }

            // Hitung ekuivalen tumpahan botol
            $spillageBottlesEquivalent = (int) intdiv($shift->spillage_ml, $standardBottleVolumeMl);

            // Perkiraan botol tersisa = opening - penjualan POS - tumpahan
            $expectedClosingBottles = max(0, $shift->opening_bottles - $shift->pos_sales_bottles - $spillageBottlesEquivalent);

            // Varians = aktual - perkiraan
            $variance = $actualClosingBottles - $expectedClosingBottles;

            $status = ($variance === 0) ? 'reconciled' : 'audited_with_variance';

            $shift->update([
                'closing_bottles' => $actualClosingBottles,
                'variance_bottles' => $variance,
                'supervisor_user_id' => $supervisorUserId,
                'status' => $status,
                'end_time' => now(),
            ]);

            return [
                'shift_log' => $shift,
                'expected_closing_bottles' => $expectedClosingBottles,
                'actual_closing_bottles' => $actualClosingBottles,
                'variance_bottles' => $variance,
                'spillage_bottles_equivalent' => $spillageBottlesEquivalent,
                'status' => $status,
            ];
        });
    }
}
