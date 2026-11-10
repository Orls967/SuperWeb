<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Treasury\Application\Services\TreasuryLedgerPoster;
use Modules\Treasury\Contracts\ExchangeRateLookup;
use Modules\Treasury\Contracts\FxMonetaryItemFeed;
use Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\Exceptions\TreasuryException;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

class RunMultiCurrencyFxRevaluationAction
{
    public function __construct(
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?ExchangeRateLookup $rateLookup = null,
        protected readonly ?FxMonetaryItemFeed $itemFeed = null,
    ) {}

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * Run multi-currency foreign exchange revaluation.
     * Exchange rate is scaled by 10,000 (e.g. 15,500 IDR/USD = 155,000,000 scaled).
     *
     * @return array{revaluation: Revaluation, gain_loss_idr: int, status: string}
     */
    public function execute(
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

            // Perhitungan nilai wajar memakai kalkulator bersertifikat D1
            $rate = RationalRate::fromScaled($spotRateScaled, 10000);
            $revaluedFunctionalIdr = $rate->multiplyAndRound($foreignBalanceMinor, 100);
            $gainLossIdr = $revaluedFunctionalIdr - $bookFunctionalIdr;

            // Generate sertifikat revaluasi
            $calculator = new FxRevaluationCalculator;
            $res = $calculator->calculateRevaluation(
                foreignBalanceMinor: $foreignBalanceMinor,
                carryingFunctionalIdr: $bookFunctionalIdr,
                rate: $rate,
                currencyDecimals: 2,
                isAsset: true
            );
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
                    'status' => 'posted',
                    'certificate' => $certificate->toArray(),
                ]
            );

            // Perbarui lot valas yang aktif
            FxLot::where('currency', $currency)
                ->where('status', 'active')
                ->update([
                    'current_carrying_rate_num' => $rate->numerator,
                    'current_carrying_rate_den' => $rate->denominator,
                    'last_revaluation_id' => $reval->id,
                ]);

            // Posting ledger jika poster tersedia dan ada selisih nilai
            if ($this->ledgerPoster !== null && $gainLossIdr !== 0) {
                $idempotencyKey = "IDEM:TRS:REVAL:{$period}:{$currency}";
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

            return [
                'revaluation' => $reval->fresh(),
                'gain_loss_idr' => $gainLossIdr,
                'status' => 'posted',
            ];
        });
    }
}
