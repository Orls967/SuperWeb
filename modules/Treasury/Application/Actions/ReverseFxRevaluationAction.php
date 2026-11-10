<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Exceptions\TreasuryException;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

class ReverseFxRevaluationAction
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
     * Membalik revaluasi periode sebelumnya pada hari pertama periode baru.
     *
     * @return array{reversal: Revaluation, original: Revaluation, status: string}
     */
    public function execute(Revaluation $original, string $newPeriod, ?int $userId = null): array
    {
        if ($original->is_reversed) {
            throw new InvalidArgumentException("Revaluasi {$original->period} {$original->currency} sudah pernah dibalik.");
        }

        return DB::transaction(function () use ($original, $newPeriod, $userId) {
            $now = $this->now();

            $gainLossToReverse = (int) $original->gain_loss_idr;

            /** @var Revaluation $reversal */
            $reversal = Revaluation::create([
                'period' => $newPeriod,
                'currency' => $original->currency,
                'foreign_balance' => $original->foreign_balance,
                'book_functional_idr' => $original->revalued_functional_idr,
                'revalued_functional_idr' => $original->book_functional_idr,
                'gain_loss_idr' => -$gainLossToReverse,
                'status' => 'reversed',
                'reverse_of' => $original->id,
                'is_reversed' => false,
            ]);

            $original->update([
                'is_reversed' => true,
                'reversed_at' => $now,
            ]);

            if ($this->ledgerPoster !== null && $gainLossToReverse !== 0) {
                $idempotencyKey = "IDEM:TRS:REVAL_REV:{$newPeriod}:{$original->currency}:{$original->id}";
                $tx = $this->ledgerPoster->postFxReversal(
                    period: $newPeriod,
                    currency: $original->currency,
                    gainLossIdr: $gainLossToReverse,
                    idempotencyKey: $idempotencyKey,
                    reversalId: $reversal->id,
                    userId: $userId
                );
                $reversal->update(['ledger_transaction_id' => $tx->id]);
            }

            return [
                'reversal' => $reversal->fresh(),
                'original' => $original->fresh(),
                'status' => 'reversed',
            ];
        });
    }
}
