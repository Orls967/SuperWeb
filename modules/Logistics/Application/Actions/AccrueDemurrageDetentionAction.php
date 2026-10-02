<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Domain\Enums\TransactionType;
use Modules\Logistics\Application\Services\LogisticsLedger;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Services\DwellChargeCalculator;

class AccrueDemurrageDetentionAction
{
    public function __construct(
        private readonly LogisticsLedger $ledger,
        private readonly DwellChargeCalculator $calculator
    ) {}

    /**
     * Alur 12: akrual kumulatif D&D. Selisih terhadap yang sudah diakrual diposting: debit piutang shipper,
     * kredit pendapatan D&D. Kunci jurnal memuat total kumulatif sehingga menjalankan ulang pada hari yang sama tidak menggandakan.
     *
     * @return int selisih rupiah yang diakrual pada pemanggilan ini
     */
    public function execute(ContainerDwell $dwell, ?CarbonInterface $asOf = null): int
    {
        return DB::transaction(function () use ($dwell, $asOf) {
            $dwell = ContainerDwell::whereKey($dwell->id)->lockForUpdate()->firstOrFail();
            $dwell->loadMissing(['location', 'tariff']);

            if (! $dwell->tariff) {
                return 0;
            }

            $reference = $dwell->ended_at ?? ($asOf ?? now());
            $timezone = $dwell->location->timezone ?: config('app.timezone');

            $elapsed = $this->calculator->daysElapsed($dwell->started_at, $reference, $timezone);
            $billable = $this->calculator->billableDays($elapsed, $dwell->tariff);
            $total = $this->calculator->amountFor($billable, $dwell->tariff);
            $delta = $total - $dwell->accrued_amount_idr;

            if ($delta > 0) {
                $this->ledger->post(
                    type: TransactionType::LOGISTICS_DD_ACCRUAL,
                    description: "Akrual {$dwell->kind} kontainer #{$dwell->container_id} ({$billable} hari berbayar)",
                    idempotencyKey: "lgx:dd:{$dwell->id}:{$total}",
                    entries: [
                        [LogisticsLedger::arCode($dwell->shipper_id), -$delta],
                        [LogisticsLedger::DD_REVENUE, $delta],
                    ],
                    referenceType: ContainerDwell::class,
                    referenceId: $dwell->id,
                );
            }

            $dwell->update([
                'billable_days' => $billable,
                'accrued_amount_idr' => max($dwell->accrued_amount_idr, $total),
                'last_accrued_on' => $reference->copy()->setTimezone($timezone)->toDateString(),
            ]);

            return max(0, $delta);
        });
    }
}
