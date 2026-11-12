<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryEventPublisher;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Events\TreasuryRevaluationPostedEvent;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

class RunFxRevaluationAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?TreasuryEventPublisher $eventPublisher = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Menjalankan revaluasi valas akhir periode PSAK 10.
     * Mendukung pemanggilan berbasis single-currency maupun batch seluruh lot aktif.
     */
    public function execute(
        string $period,
        ?string $asOfDate = null,
        ?string $currency = null,
        ?int $foreignBalanceMinor = null,
        ?int $bookFunctionalIdr = null,
        ?int $spotRateScaled = null,
        ?int $userId = null
    ): mixed {
        // Mode 1: Pemanggilan spesifik single currency
        if ($currency !== null && $foreignBalanceMinor !== null && $bookFunctionalIdr !== null && $spotRateScaled !== null) {
            return $this->executeSingle(
                period: $period,
                currency: $currency,
                foreignBalanceMinor: $foreignBalanceMinor,
                bookFunctionalIdr: $bookFunctionalIdr,
                spotRateScaled: $spotRateScaled,
                userId: $userId
            );
        }

        // Mode 2: Pemanggilan batch seluruh lot valas aktif per tanggal akhir periode
        return DB::transaction(function () use ($period, $asOfDate, $userId) {
            $date = $asOfDate ?? $this->now()->toDateString();
            $lots = FxLot::where('status', 'active')->get();

            $results = [];
            $calculator = new FxRevaluationCalculator;

            // Kelompokkan saldo dan book value per currency
            $byCurrency = $lots->groupBy('currency');

            foreach ($byCurrency as $curr => $currencyLots) {
                $curr = strtoupper((string) $curr);
                $totalForeign = (int) $currencyLots->sum('remaining_amount');
                $totalBook = (int) $currencyLots->sum('current_carrying_idr');

                $rateRecord = ExchangeRate::where('from_currency', $curr)
                    ->where('to_currency', 'IDR')
                    ->whereDate('rate_date', '<=', $date)
                    ->orderByDesc('rate_date')
                    ->first();

                $rate = $rateRecord !== null
                    ? new RationalRate((int) $rateRecord->rate_numerator, (int) $rateRecord->rate_denominator)
                    : new RationalRate(15650, 1);

                $calcRes = $calculator->calculateRevaluation(
                    foreignBalanceMinor: $totalForeign,
                    carryingFunctionalIdr: $totalBook,
                    rate: $rate,
                    currencyDecimals: 2,
                    isAsset: true
                );
                $revaluedIdr = $calcRes->revaluedFunctionalIdr;
                $gainLossIdr = $calcRes->unrealizedGainLossIdr;
                $cert = $calcRes->certificate;

                /** @var Revaluation $reval */
                $reval = Revaluation::updateOrCreate(
                    [
                        'period' => $period,
                        'currency' => $curr,
                    ],
                    [
                        'foreign_balance' => $totalForeign,
                        'book_functional_idr' => $totalBook,
                        'revalued_functional_idr' => $revaluedIdr,
                        'gain_loss_idr' => $gainLossIdr,
                        'status' => 'completed',
                        'is_reversed' => false,
                        'certificate' => $cert->toArray(),
                    ]
                );

                // Update carrying rate lot
                foreach ($currencyLots as $l) {
                    $lotRes = $calculator->calculateRevaluation(
                        foreignBalanceMinor: (int) $l->remaining_amount,
                        carryingFunctionalIdr: (int) $l->current_carrying_idr,
                        rate: $rate,
                        currencyDecimals: 2,
                        isAsset: true
                    );
                    $l->update([
                        'current_carrying_rate_num' => $rate->numerator,
                        'current_carrying_rate_den' => $rate->denominator,
                        'current_carrying_idr' => $lotRes->revaluedFunctionalIdr,
                        'last_revaluation_id' => $reval->id,
                    ]);
                }

                // Posting ke ledger jika ada selisih
                if ($this->ledgerPoster !== null && $gainLossIdr !== 0) {
                    $idempotencyKey = "IDEM:TRS:REVAL:{$period}:{$curr}:{$reval->id}";
                    $tx = $this->ledgerPoster->postFxRevaluation(
                        period: $period,
                        currency: $curr,
                        gainLossIdr: $gainLossIdr,
                        idempotencyKey: $idempotencyKey,
                        revaluationId: $reval->id,
                        userId: $userId
                    );
                    $reval->update(['ledger_transaction_id' => $tx->id]);
                }

                $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);
                if ($publisher !== null) {
                    $publisher->publishRevaluationPosted(new TreasuryRevaluationPostedEvent(
                        revaluationId: (string) $reval->id,
                        period: $period,
                        currency: $curr,
                        foreignBalanceMinor: $totalForeign,
                        bookFunctionalIdr: $totalBook,
                        revaluedFunctionalIdr: $revaluedIdr,
                        gainLossIdr: $gainLossIdr,
                        certificateHash: $cert->certificateHash,
                        ledgerTransactionId: $reval->ledger_transaction_id ? (int) $reval->ledger_transaction_id : null,
                        userId: $userId
                    ));
                }

                $results[] = $reval->fresh();
            }

            return $results;
        });
    }

    protected function executeSingle(
        string $period,
        string $currency,
        int $foreignBalanceMinor,
        int $bookFunctionalIdr,
        int $spotRateScaled,
        ?int $userId = null
    ): array {
        if ($foreignBalanceMinor < 0 || $bookFunctionalIdr < 0 || $spotRateScaled <= 0) {
            throw new InvalidArgumentException('Saldo valas, saldo buku IDR, dan kurs spot harus bernilai positif.');
        }

        return DB::transaction(function () use ($period, $currency, $foreignBalanceMinor, $bookFunctionalIdr, $spotRateScaled, $userId) {
            $currency = strtoupper(trim($currency));

            $rate = RationalRate::fromScaled($spotRateScaled, 10000);
            $calculator = new FxRevaluationCalculator;
            $res = $calculator->calculateRevaluation(
                foreignBalanceMinor: $foreignBalanceMinor,
                carryingFunctionalIdr: $bookFunctionalIdr,
                rate: $rate,
                currencyDecimals: 2,
                isAsset: true
            );
            $revaluedFunctionalIdr = $res->revaluedFunctionalIdr;
            $gainLossIdr = $res->unrealizedGainLossIdr;
            $certificate = $res->certificate;

            /** @var Revaluation $reval */
            $reval = Revaluation::updateOrCreate(
                [
                    'period' => $period,
                    'currency' => $currency,
                ],
                [
                    'foreign_balance' => $foreignBalanceMinor,
                    'book_functional_idr' => $bookFunctionalIdr,
                    'revalued_functional_idr' => $revaluedFunctionalIdr,
                    'gain_loss_idr' => $gainLossIdr,
                    'status' => 'completed',
                    'is_reversed' => false,
                    'certificate' => $certificate->toArray(),
                ]
            );

            FxLot::where('currency', $currency)
                ->where('status', 'active')
                ->update([
                    'current_carrying_rate_num' => $rate->numerator,
                    'current_carrying_rate_den' => $rate->denominator,
                    'last_revaluation_id' => $reval->id,
                ]);

            if ($this->ledgerPoster !== null && $gainLossIdr !== 0) {
                $idempotencyKey = "IDEM:TRS:REVAL:{$period}:{$currency}:{$reval->id}";
                $tx = $this->ledgerPoster->postFxRevaluation(
                    period: $period,
                    currency: $currency,
                    gainLossIdr: $gainLossIdr,
                    idempotencyKey: $idempotencyKey,
                    revaluationId: $reval->id,
                    userId: $userId
                );
                $reval->update(['ledger_transaction_id' => $tx->id]);
            }

            $publisher = $this->eventPublisher ?? (app()->bound(TreasuryEventPublisher::class) ? app(TreasuryEventPublisher::class) : null);
            if ($publisher !== null) {
                $publisher->publishRevaluationPosted(new TreasuryRevaluationPostedEvent(
                    revaluationId: (string) $reval->id,
                    period: $period,
                    currency: $currency,
                    foreignBalanceMinor: $foreignBalanceMinor,
                    bookFunctionalIdr: $bookFunctionalIdr,
                    revaluedFunctionalIdr: $revaluedFunctionalIdr,
                    gainLossIdr: $gainLossIdr,
                    certificateHash: $certificate->certificateHash,
                    ledgerTransactionId: $reval->ledger_transaction_id ? (int) $reval->ledger_transaction_id : null,
                    userId: $userId
                ));
            }

            return [
                'revaluation' => $reval->fresh(),
                'gain_loss_idr' => $gainLossIdr,
                'status' => 'completed',
            ];
        });
    }
}
