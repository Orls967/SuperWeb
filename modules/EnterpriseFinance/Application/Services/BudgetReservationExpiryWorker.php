<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Application\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\EnterpriseFinance\Domain\Models\BudgetHistory;
use Modules\EnterpriseFinance\Domain\Models\EnterpriseBudget;
use Psr\Clock\ClockInterface;

/**
 * Pekerja Pembebasan Reservasi Anggaran Kedaluwarsa (54.1).
 * Mengembalikan pagu komitmen (encumbrance) yang telah lewat batas waktu transaksi
 * tanpa realisasi faktur belanja (unencumbering stale reservations) agar pagu kas tidak beku.
 */
class BudgetReservationExpiryWorker
{
    public function __construct(
        protected readonly ?ClockInterface $clock = null
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Memeriksa dan membebaskan komitmen encumbrance yang tidak aktif lebih dari batas hari tertentu.
     *
     * @return array{
     *     budgets_scanned: int,
     *     budgets_adjusted: int,
     *     total_amount_released_idr: int,
     *     executed_at: string
     * }
     */
    public function releaseStaleEncumbrances(int $expiryDays = 30, ?int $operatorId = null): array
    {
        $cutoff = $this->now()->subDays($expiryDays);

        $budgets = EnterpriseBudget::where('encumbered_amount_idr', '>', 0)
            ->where('updated_at', '<', $cutoff)
            ->get();

        $adjustedCount = 0;
        $totalReleased = 0;

        foreach ($budgets as $budget) {
            DB::transaction(function () use ($budget, &$adjustedCount, &$totalReleased, $operatorId, $expiryDays) {
                $locked = EnterpriseBudget::where('id', $budget->id)->lockForUpdate()->firstOrFail();
                $releasedAmount = $locked->encumbered_amount_idr;

                if ($releasedAmount > 0) {
                    $locked->encumbered_amount_idr = 0;
                    $locked->save();

                    BudgetHistory::create([
                        'budget_id' => $locked->id,
                        'from_status' => $locked->status ?? 'ACTIVE',
                        'to_status' => $locked->status ?? 'ACTIVE',
                        'reason' => "Pembebasan encumbrance otomatis Rp {$releasedAmount} karena inaktivitas > {$expiryDays} hari",
                        'operator_id' => $operatorId,
                    ]);

                    $adjustedCount++;
                    $totalReleased += $releasedAmount;
                }
            });
        }

        return [
            'budgets_scanned' => $budgets->count(),
            'budgets_adjusted' => $adjustedCount,
            'total_amount_released_idr' => $totalReleased,
            'executed_at' => $this->now()->toIso8601String(),
        ];
    }
}
