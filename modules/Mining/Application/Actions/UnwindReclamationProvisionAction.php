<?php

declare(strict_types=1);

namespace Modules\Mining\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Mining\Domain\Models\MiningReclamationSchedule;
use Modules\Mining\Domain\Models\MiningSite;
use Modules\Mining\Ledger\MiningFase94LedgerPostingService;

class UnwindReclamationProvisionAction
{
    public function __construct(
        private readonly MiningFase94LedgerPostingService $ledgerPosting
    ) {}

    /**
     * Hitung dan lakukan penyesuaian diskonto nilai kini (unwinding of discount)
     * atas provisi kewajiban reklamasi dan penutupan tambang (D94-6, PSAK 57 / IAS 37).
     *
     * @param array{
     *     site_id: string,
     *     year: int,
     *     quarter: int,
     *     discount_rate_bps: int
     * } $data
     * @return array{
     *     site_id: string,
     *     period: string,
     *     total_provision_idr: int,
     *     unwind_expense_idr: int,
     *     schedules_count: int,
     *     status: string
     * }
     */
    public function execute(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $siteId = (string) $data['site_id'];
            $year = (int) $data['year'];
            $quarter = (int) $data['quarter'];
            $discountRateBps = (int) ($data['discount_rate_bps'] ?? 650); // 6.50% suku bunga diskonto BI/SBN

            if ($quarter < 1 || $quarter > 4) {
                throw new InvalidArgumentException('Kuartal harus berada di antara 1 dan 4.');
            }

            $site = MiningSite::findOrFail($siteId);

            $schedules = MiningReclamationSchedule::where('site_id', $site->id)
                ->where('planned_year', $year)
                ->where('planned_quarter', $quarter)
                ->get();

            if ($schedules->isEmpty()) {
                throw new DomainException("Tidak ditemukan jadwal reklamasi aktif untuk site {$site->site_code} pada periode {$year} Q{$quarter}.");
            }

            $totalProvisionIdr = (int) $schedules->sum(fn ($s) => $s->provision_amount_minor ?? $s->estimated_cost_idr);

            // Unwinding beban bunga kuartalan = Total Provisi * (Suku Bunga Tahunan / 4)
            $quarterlyDiscountRateBps = intdiv($discountRateBps, 4);
            $unwindExpenseIdr = intdiv($totalProvisionIdr * $quarterlyDiscountRateBps, 10000);

            if ($unwindExpenseIdr > 0) {
                $scheduleId = (string) $schedules->first()->id;
                $periodCode = "{$year}Q{$quarter}";

                $this->ledgerPosting->postReclamationUnwinding(
                    scheduleId: $scheduleId,
                    projectNumber: $schedules->first()->project_number ?? "REC-PRJ-{$year}Q{$quarter}",
                    amountIdr: $unwindExpenseIdr
                );
            }

            return [
                'site_id' => $siteId,
                'period' => "{$year} Q{$quarter}",
                'total_provision_idr' => $totalProvisionIdr,
                'unwind_expense_idr' => $unwindExpenseIdr,
                'schedules_count' => $schedules->count(),
                'status' => 'UNWOUND_POSTED',
            ];
        });
    }
}
