<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelTimeshareMaintenanceFundLedgerService
{
    /**
     * Membukukan pengeluaran perbaikan & pemeliharaan vila dari rekening penampungan sinking fund pemilik.
     *
     * @return array{repair_work_order_id: string, timeshare_unit_id: string, repair_category: string, expense_amount_idr: int, ledger_debit: string, ledger_credit: string, timestamp: string}
     */
    public function recordMaintenanceExpense(
        string $timeshareUnitId,
        string $repairCategory, // ROOF_REPAIR, POOL_MAINTENANCE, HVAC_OVERHAUL, FURNITURE_REFURBISHMENT
        int $expenseAmountIdr,
        string $contractorReference
    ): array {
        $unit = TimeshareUnit::findOrFail($timeshareUnitId);

        if ($expenseAmountIdr <= 0) {
            throw new InvalidArgumentException('Biaya perbaikan pemeliharaan vila harus lebih dari nol.');
        }

        $workOrderId = 'WO-TS-REP-'.strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $debitAccount = "htl:expense:timeshare_repair:{$unit->unit_code}:IDR";
        $creditAccount = "htl:sinking_fund:timeshare:{$unit->unit_code}:IDR";

        return [
            'repair_work_order_id' => $workOrderId,
            'timeshare_unit_id' => $unit->id,
            'repair_category' => $repairCategory,
            'expense_amount_idr' => $expenseAmountIdr,
            'ledger_debit' => $debitAccount,
            'ledger_credit' => $creditAccount,
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
