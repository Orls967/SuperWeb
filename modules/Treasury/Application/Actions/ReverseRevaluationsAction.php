<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

class ReverseRevaluationsAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Membalik seluruh revaluasi kurs pada periode tertentu pada awal periode berikutnya.
     */
    public function execute(string $period, ?int $userId = null): int
    {
        return DB::transaction(function () use ($period, $userId) {
            $now = $this->now();
            $revaluations = Revaluation::where('period', $period)
                ->where('is_reversed', false)
                ->where('status', 'completed')
                ->get();

            $reversedCount = 0;
            $nextPeriod = $now->format('Y-m');

            foreach ($revaluations as $reval) {
                $gainLossToReverse = (int) $reval->gain_loss_idr;

                $reval->update([
                    'is_reversed' => true,
                    'reversed_at' => $now,
                ]);

                if ($this->ledgerPoster !== null && $gainLossToReverse !== 0) {
                    $idempotencyKey = "IDEM:TRS:REVAL_REV:{$period}:{$reval->currency}:{$reval->id}";
                    $tx = $this->ledgerPoster->postFxReversal(
                        period: $period,
                        currency: $reval->currency,
                        gainLossIdr: $gainLossToReverse,
                        idempotencyKey: $idempotencyKey,
                        reversalId: $reval->id,
                        userId: $userId
                    );
                }

                $reversedCount++;
            }

            // Kembalikan carrying rate lot valas ke kurs perolehan awal (karena jurnal revaluasi telah dibalik)
            $currencies = $revaluations->pluck('currency')->unique();
            FxLot::where('status', 'active')
                ->whereIn('currency', $currencies)
                ->get()
                ->each(function (FxLot $lot) {
                    $lot->update([
                        'current_carrying_rate_num' => $lot->original_rate_num,
                        'current_carrying_rate_den' => $lot->original_rate_den,
                        'current_carrying_idr' => (int) intdiv($lot->remaining_amount * $lot->original_rate_num, 100 * $lot->original_rate_den),
                    ]);
                });

            return $reversedCount;
        });
    }
}
