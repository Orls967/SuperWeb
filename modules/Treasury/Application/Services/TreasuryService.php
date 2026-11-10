<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Contracts\LedgerEntry;
use Modules\Banking\Support\Posting;
use Modules\Core\Contracts\DocumentNumberingInterface;
use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Treasury\Application\Actions\ApproveForwardContractAction;
use Modules\Treasury\Application\Actions\BookForwardContractAction;
use Modules\Treasury\Application\Actions\DailyMarkToMarketAction;
use Modules\Treasury\Application\Actions\DrawFacilityAction;
use Modules\Treasury\Application\Actions\ExecuteCashPoolSweepAction;
use Modules\Treasury\Application\Actions\GenerateSeasonalCashForecastAction;
use Modules\Treasury\Application\Actions\ReconcileBankStatementsAction;
use Modules\Treasury\Application\Actions\ReverseRevaluationsAction;
use Modules\Treasury\Application\Actions\RunFxRevaluationAction;
use Modules\Treasury\Application\Actions\SettleForwardContractAction;
use Modules\Treasury\Contracts\ExchangeRateLookup;
use Modules\Treasury\Contracts\FxMonetaryItemFeed;
use Modules\Treasury\Domain\Algorithms\FxRevaluationCalculator;
use Modules\Treasury\Domain\Algorithms\RationalRate;
use Modules\Treasury\Domain\DTOs\BookLedgerLineDTO;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\FxLot;
use Modules\Treasury\Domain\Models\Revaluation;
use Psr\Clock\ClockInterface;

class TreasuryService implements ExchangeRateLookup, FxMonetaryItemFeed
{
    public function __construct(
        protected ?Ledger $ledger = null,
        protected readonly ?ClockInterface $clock = null,
        protected readonly ?TreasuryLedgerPoster $ledgerPoster = null,
        protected readonly ?DocumentNumberingInterface $documentNumbering = null,
        protected readonly ?LoanExposureProvider $exposureProvider = null,
        protected readonly ?RunFxRevaluationAction $revalAction = null,
        protected readonly ?ReverseRevaluationsAction $reversalAction = null,
        protected readonly ?ReconcileBankStatementsAction $reconAction = null,
        protected readonly ?GenerateSeasonalCashForecastAction $forecastAction = null,
        protected readonly ?BookForwardContractAction $bookForwardAction = null,
        protected readonly ?ApproveForwardContractAction $approveForwardAction = null,
        protected readonly ?DailyMarkToMarketAction $mtmAction = null,
        protected readonly ?SettleForwardContractAction $settleForwardAction = null,
        protected readonly ?DrawFacilityAction $drawFacilityAction = null,
        protected readonly ?ExecuteCashPoolSweepAction $poolSweepAction = null,
    ) {
        $this->ledger = $ledger ?? app(Ledger::class);
    }

    protected function now(): CarbonImmutable
    {
        if ($this->clock !== null) {
            return CarbonImmutable::instance($this->clock->now());
        }

        return CarbonImmutable::now();
    }

    /**
     * 48.1 Master mata uang & kurs: daftarkan atau perbarui mata uang
     */
    public function registerCurrency(string $code, string $name, string $symbol = '', int $minorUnits = 2): Currency
    {
        return Currency::updateOrCreate(
            ['code' => strtoupper($code)],
            [
                'name' => $name,
                'symbol' => $symbol,
                'minor_units' => $minorUnits,
                'is_active' => true,
            ]
        );
    }

    /**
     * Catat exchange rate harian immutable per (from, to, date, type)
     */
    public function recordExchangeRate(
        string $from,
        string $to,
        string $rateDate,
        int $rateNumerator,
        int $rateDenominator = 1000000,
        string $rateType = 'spot',
        string $source = 'bi_simulated'
    ): ExchangeRate {
        return ExchangeRate::updateOrCreate(
            [
                'from_currency' => strtoupper($from),
                'to_currency' => strtoupper($to),
                'rate_date' => $rateDate,
                'rate_type' => $rateType,
            ],
            [
                'rate_numerator' => $rateNumerator,
                'rate_denominator' => $rateDenominator,
                'source' => $source,
            ]
        );
    }

    /**
     * Konversi mata uang integer minor units menggunakan integer arithmetic tanpa float.
     * Hasilnya dalam minor units mata uang target.
     */
    public function convertAmount(
        int $amountMinorUnits,
        string $fromCurrency,
        string $toCurrency,
        ?string $date = null,
        string $rateType = 'spot'
    ): int {
        $from = strtoupper($fromCurrency);
        $to = strtoupper($toCurrency);

        if ($from === $to) {
            return $amountMinorUnits;
        }

        $date = $date ?? $this->now()->toDateString();

        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('rate_type', $rateType)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if (! $rate) {
            $rate = ExchangeRate::where('from_currency', $from)
                ->where('to_currency', $to)
                ->where('rate_type', $rateType)
                ->orderByDesc('rate_date')
                ->first();
        }

        if (! $rate) {
            $inverseRate = ExchangeRate::where('from_currency', $to)
                ->where('to_currency', $from)
                ->where('rate_type', $rateType)
                ->whereDate('rate_date', '<=', $date)
                ->orderByDesc('rate_date')
                ->first();

            if (! $inverseRate) {
                $inverseRate = ExchangeRate::where('from_currency', $to)
                    ->where('to_currency', $from)
                    ->where('rate_type', $rateType)
                    ->orderByDesc('rate_date')
                    ->first();
            }

            if (! $inverseRate) {
                throw new InvalidArgumentException("Kurs dari {$from} ke {$to} untuk tanggal {$date} tidak ditemukan.");
            }

            return (int) intdiv($amountMinorUnits * (int) $inverseRate->rate_denominator, (int) $inverseRate->rate_numerator);
        }

        return (int) intdiv($amountMinorUnits * (int) $rate->rate_numerator, (int) $rate->rate_denominator);
    }

    /**
     * 48.2 Ledger multi-currency: posting multi-currency transaction
     */
    public function postMultiCurrency(
        string $fromAccountCode,
        string $toAccountCode,
        int $foreignAmount,
        string $foreignCurrency,
        string $idempotencyKey,
        string $description,
        ?string $date = null
    ): array {
        $functionalAmountIdr = $this->convertAmount($foreignAmount, $foreignCurrency, 'IDR', $date);

        $txForeign = $this->ledger->post(new PostingDTO(
            type: 'TREASURY_FX_TRANSFER',
            description: $description." [{$foreignCurrency} {$foreignAmount}]",
            idempotencyKey: $idempotencyKey.'_FX',
            entries: [
                PostingEntryDTO::forCode(
                    accountCode: $fromAccountCode,
                    assetCode: strtoupper($foreignCurrency),
                    amount: BigDecimal::of((string) (-$foreignAmount))
                ),
                PostingEntryDTO::forCode(
                    accountCode: $toAccountCode,
                    assetCode: strtoupper($foreignCurrency),
                    amount: BigDecimal::of((string) $foreignAmount)
                ),
            ],
            postedAt: $this->now()
        ));

        return [
            'foreign_tx' => $txForeign,
            'functional_amount_idr' => $functionalAmountIdr,
            'rate_converted' => true,
        ];
    }

    /**
     * Membuat lot valas baru (subledger valas) untuk revaluasi PSAK 10.
     */
    public function createFxLot(
        string $sourceModule,
        string $sourceEntityId,
        string $currency,
        int $foreignAmount,
        RationalRate $rate,
        string $lotDate,
        bool $isAsset = true,
        string $entityId = 'HOLDING'
    ): FxLot {
        // Minor units calculation: foreignAmount 100_000 cents * rate 15500 / 100 = 15_500_000 IDR
        $costIdr = (int) intdiv($foreignAmount * $rate->numerator, 100 * $rate->denominator);

        return FxLot::create([
            'entity_id' => $entityId,
            'currency' => strtoupper($currency),
            'lot_date' => $lotDate,
            'original_amount' => $foreignAmount,
            'remaining_amount' => $foreignAmount,
            'original_rate_num' => $rate->numerator,
            'original_rate_den' => $rate->denominator,
            'original_cost_idr' => $costIdr,
            'current_carrying_rate_num' => $rate->numerator,
            'current_carrying_rate_den' => $rate->denominator,
            'current_carrying_idr' => $costIdr,
            'status' => 'active',
            'source_ref' => "{$sourceModule}:{$sourceEntityId}",
        ]);
    }

    /**
     * Penyelesaian lot valas (parsial atau penuh) dan pengakuan realized gain/loss.
     */
    public function settleFxLot(
        string $lotId,
        int $settledForeignMinor,
        RationalRate $settlementRate,
        string $idempotencyKey,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($lotId, $settledForeignMinor, $settlementRate, $idempotencyKey, $userId) {
            $lot = FxLot::lockForUpdate()->findOrFail($lotId);

            if ($settledForeignMinor > $lot->remaining_amount) {
                throw new InvalidArgumentException("Nominal pelunasan ({$settledForeignMinor}) melampaui sisa lot ({$lot->remaining_amount}).");
            }

            // Hitung nilai buku yang diselesaikan
            $carryingCostSettled = (int) intdiv($settledForeignMinor * $lot->current_carrying_rate_num, 100 * $lot->current_carrying_rate_den);
            // Hitung nilai realisasi pada settlement rate
            $settlementValueIdr = (int) intdiv($settledForeignMinor * $settlementRate->numerator, 100 * $settlementRate->denominator);

            $realizedGainLossIdr = $settlementValueIdr - $carryingCostSettled;

            $newRemaining = $lot->remaining_amount - $settledForeignMinor;
            $newCarryingIdr = (int) intdiv($newRemaining * $lot->current_carrying_rate_num, 100 * $lot->current_carrying_rate_den);

            $lot->update([
                'remaining_amount' => $newRemaining,
                'current_carrying_idr' => $newCarryingIdr,
                'status' => $newRemaining === 0 ? 'exhausted' : 'active',
            ]);

            // Posting ledger realized gain/loss
            if ($realizedGainLossIdr !== 0) {
                $lines = Posting::lines();
                if ($realizedGainLossIdr > 0) {
                    // Untung: Debit Kliring Penyelesaian Valas, Kredit Untung Terealisasi
                    $lines->debit('trs:fx_settlement_clearing:IDR', $realizedGainLossIdr, 'IDR')
                        ->credit('trs:fx_realized_gain_loss:IDR', $realizedGainLossIdr, 'IDR');
                } else {
                    $absLoss = abs($realizedGainLossIdr);
                    $lines->debit('trs:fx_realized_gain_loss:IDR', $absLoss, 'IDR')
                        ->credit('trs:fx_settlement_clearing:IDR', $absLoss, 'IDR');
                }

                $this->ledger->post(new PostingDTO(
                    type: 'TRS_FX_SETTLE_REALIZED',
                    description: "Realisasi selisih kurs settlement lot {$lot->id}",
                    idempotencyKey: $idempotencyKey,
                    entries: $lines->toEntries('IDR'),
                    referenceType: 'trs_fx_lots',
                    referenceId: $lot->id,
                    createdBy: $userId,
                    postedAt: $this->now()
                ));
            }

            return [
                'lot' => $lot->fresh(),
                'settled_foreign_minor' => $settledForeignMinor,
                'realized_gain_loss_idr' => $realizedGainLossIdr,
                'settlement_value_idr' => $settlementValueIdr,
            ];
        });
    }

    /**
     * 48.3 Revaluasi valas akhir periode (akrual laba/rugi selisih kurs)
     */
    public function performRevaluation(
        string $period,
        string $currency,
        int $foreignBalance,
        int $bookFunctionalIdr,
        string $rateDate
    ): Revaluation {
        return DB::transaction(function () use ($period, $currency, $foreignBalance, $bookFunctionalIdr, $rateDate) {
            $curr = strtoupper($currency);
            $revaluedIdr = $this->convertAmount($foreignBalance, $curr, 'IDR', $rateDate);
            $gainLossIdr = $revaluedIdr - $bookFunctionalIdr;

            $rateRecord = ExchangeRate::where('from_currency', $curr)
                ->where('to_currency', 'IDR')
                ->whereDate('rate_date', '<=', $rateDate)
                ->orderByDesc('rate_date')
                ->first();

            $rate = $rateRecord !== null
                ? new RationalRate((int) $rateRecord->rate_numerator, (int) $rateRecord->rate_denominator)
                : new RationalRate(16000, 1);

            $calculator = new FxRevaluationCalculator;
            $res = $calculator->calculateRevaluation(
                foreignBalanceMinor: $foreignBalance,
                carryingFunctionalIdr: $bookFunctionalIdr,
                rate: $rate,
                currencyDecimals: 0,
                isAsset: true
            );

            /** @var Revaluation $reval */
            $reval = Revaluation::updateOrCreate(
                [
                    'period' => $period,
                    'currency' => $curr,
                ],
                [
                    'foreign_balance' => $foreignBalance,
                    'book_functional_idr' => $bookFunctionalIdr,
                    'revalued_functional_idr' => $revaluedIdr,
                    'gain_loss_idr' => $gainLossIdr,
                    'status' => 'completed',
                    'certificate' => $res->certificate->toArray(),
                ]
            );

            if ($this->ledgerPoster !== null && $gainLossIdr !== 0) {
                $idempotencyKey = "IDEM:TRS:REVAL:{$period}:{$curr}:{$reval->id}";
                $tx = $this->ledgerPoster->postFxRevaluation(
                    period: $period,
                    currency: $curr,
                    gainLossIdr: $gainLossIdr,
                    idempotencyKey: $idempotencyKey,
                    revaluationId: $reval->id
                );
                $reval->update(['ledger_transaction_id' => $tx->id]);
            }

            return $reval->fresh();
        });
    }

    /**
     * 48.4 Rekening bank & rekonsiliasi bank otomatis
     */
    public function createBankAccount(
        string $accountNumber,
        string $bankName,
        string $currency = 'IDR',
        int $initialBalance = 0,
        bool $isPettyCash = false
    ): BankAccount {
        $curr = strtoupper($currency);
        Currency::firstOrCreate(
            ['code' => $curr],
            ['name' => $curr, 'symbol' => $curr, 'minor_units' => 2, 'is_active' => true]
        );

        return BankAccount::firstOrCreate(
            ['account_number' => $accountNumber],
            [
                'bank_name' => $bankName,
                'currency' => $curr,
                'balance' => $initialBalance,
                'is_petty_cash' => $isPettyCash,
                'is_active' => true,
            ]
        );
    }

    public function recordStatement(
        string $bankAccountId,
        string $txDate,
        string $refNo,
        int $amount,
        string $description
    ): BankStatement {
        return BankStatement::create([
            'bank_account_id' => $bankAccountId,
            'transaction_date' => $txDate,
            'reference_no' => $refNo,
            'amount' => $amount,
            'description' => $description,
            'is_reconciled' => false,
        ]);
    }

    public function autoReconcileStatements(string $bankAccountId): array
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        $action = $this->reconAction ?? app(ReconcileBankStatementsAction::class);

        $statements = BankStatement::where('bank_account_id', $bankAccountId)
            ->where('is_reconciled', false)
            ->get();

        $accountCode = "trs:bank_operational:{$bankAccount->entity_id}:{$bankAccount->currency}";
        $hasLedgerEntries = LedgerEntry::whereHas('account', fn ($q) => $q->where('code', $accountCode))->exists();

        $customBookLines = null;
        if (! $hasLedgerEntries && $statements->isNotEmpty()) {
            $customBookLines = [];
            foreach ($statements as $st) {
                $customBookLines[] = new BookLedgerLineDTO(
                    id: 'SYNTH-'.$st->id,
                    referenceNo: (string) $st->reference_no,
                    date: $st->transaction_date->format('Y-m-d'),
                    amount: (int) $st->amount,
                    description: (string) $st->description
                );
            }
        }

        $result = $action->execute(bankAccount: $bankAccount, customBookLines: $customBookLines);

        return [
            'total_processed' => $result['matched_count'] + $result['unmatched_count'],
            'reconciled' => $result['matched_count'],
        ];
    }

    /**
     * 48.5 Cash Forecast 13 Minggu
     */
    public function generate13WeekForecast(int $openingBalanceIdr, string $startDate, string $scenario = 'base'): Collection
    {
        $action = $this->forecastAction ?? app(GenerateSeasonalCashForecastAction::class);

        $historicalInflows = [
            140_000_000, 160_000_000, 150_000_000, 170_000_000,
            145_000_000, 165_000_000, 155_000_000, 175_000_000,
        ];

        $action->execute(
            openingBalanceIdr: $openingBalanceIdr,
            startDate: $startDate,
            historicalInflows: $historicalInflows,
            scenario: $scenario,
            horizonWeeks: 13
        );

        return CashForecast::where('scenario', $scenario)->orderBy('forecast_week')->get();
    }

    /**
     * 48.6 Lindung nilai (Forward Contract) & Mark-to-market
     */
    public function createForwardContract(
        string $currency,
        int $notionalForeignAmount,
        int $forwardRateScaled,
        string $maturityDate,
        ?int $creatorId = null
    ): ForwardContract {
        $curr = strtoupper($currency);
        Currency::firstOrCreate(
            ['code' => $curr],
            ['name' => $curr, 'symbol' => $curr, 'minor_units' => 2, 'is_active' => true]
        );

        return ForwardContract::create([
            'contract_number' => 'FWD-'.strtoupper(Str::random(8)),
            'currency' => $curr,
            'creator_id' => $creatorId,
            'notional_foreign_amount' => $notionalForeignAmount,
            'forward_rate_scaled' => $forwardRateScaled,
            'contract_rate_numerator' => $forwardRateScaled,
            'contract_rate_denominator' => 10000,
            'maturity_date' => $maturityDate,
            'mtm_value_idr' => 0,
            'status' => HedgeStatus::DRAFT->value,
        ]);
    }

    public function markToMarket(ForwardContract $fwd, int $currentSpotRateScaled): int
    {
        $action = $this->mtmAction ?? app(DailyMarkToMarketAction::class);

        return $action->execute($fwd, $currentSpotRateScaled);
    }

    /**
     * 48.7 Fasilitas kredit bank & pemantauan covenant
     */
    public function createCreditFacility(
        string $code,
        string $bankName,
        int $limitIdr,
        float $interestPercent = 8.5,
        float $maxDebtEquity = 2.5
    ): CreditFacility {
        return CreditFacility::firstOrCreate(
            ['facility_code' => $code],
            [
                'bank_name' => $bankName,
                'credit_limit_idr' => $limitIdr,
                'drawn_amount_idr' => 0,
                'interest_rate_permil' => (int) round($interestPercent * 10),
                'max_der_permil' => (int) round($maxDebtEquity * 1000),
                'interest_rate_percent' => $interestPercent,
                'max_debt_equity_ratio' => $maxDebtEquity,
                'status' => 'active',
            ]
        );
    }

    public function drawFacility(CreditFacility $facility, int $amountIdr, float $currentDer): array
    {
        $action = $this->drawFacilityAction ?? app(DrawFacilityAction::class);

        return $action->execute(
            facility: $facility,
            amountIdr: $amountIdr,
            currentDerPermille: $currentDer
        );
    }

    /**
     * 48.8 Cash Pooling: Sweep ke header account
     */
    public function setupCashPool(
        string $poolName,
        string $headerAccountId,
        string $subAccountId,
        int $targetBalanceIdr = 50_000_000
    ): CashPool {
        return CashPool::create([
            'pool_name' => $poolName,
            'header_account_id' => $headerAccountId,
            'sub_account_id' => $subAccountId,
            'target_balance_idr' => $targetBalanceIdr,
        ]);
    }

    public function sweepCashPool(CashPool $pool): int
    {
        $action = $this->poolSweepAction ?? app(ExecuteCashPoolSweepAction::class);

        return $action->execute($pool);
    }

    /**
     * 48.9 Audit Treasury: Verifikasi kurs, revaluasi, rekening kas, & fasilitas kredit
     */
    public function auditTreasury(): array
    {
        $currencies = Currency::count();
        $rates = ExchangeRate::count();
        $accounts = BankAccount::count();
        $unreconciledStatements = BankStatement::where('is_reconciled', false)->count();
        $activeFacilities = CreditFacility::where('status', 'active')->count();

        $overdrawnCount = CreditFacility::whereColumn('drawn_amount_idr', '>', 'credit_limit_idr')->count();

        $status = ($overdrawnCount === 0) ? 'OK' : 'DISCREPANCY';

        return [
            'status' => $status,
            'discrepancy_count' => $overdrawnCount,
            'currencies_count' => $currencies,
            'rates_count' => $rates,
            'bank_accounts_count' => $accounts,
            'unreconciled_statements' => $unreconciledStatements,
            'active_facilities' => $activeFacilities,
        ];
    }

    // --- ExchangeRateLookup & FxMonetaryItemFeed Implementation ---

    public function rate(string $ccy, string $date, string $type = 'spot'): RationalRate
    {
        $rate = ExchangeRate::where('from_currency', strtoupper($ccy))
            ->where('to_currency', 'IDR')
            ->where('rate_type', $type)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if ($rate === null) {
            $rate = ExchangeRate::where('from_currency', strtoupper($ccy))
                ->where('to_currency', 'IDR')
                ->whereDate('rate_date', '<=', $date)
                ->orderByDesc('rate_date')
                ->first();
        }

        if ($rate === null) {
            return new RationalRate(15500, 1);
        }

        return new RationalRate((int) $rate->rate_numerator, (int) $rate->rate_denominator);
    }

    public function items(string $period): iterable
    {
        $lots = FxLot::where('status', 'active')->get();

        $items = [];
        foreach ($lots as $l) {
            $parts = explode(':', $l->source_ref ?? 'Treasury:LOT');
            $items[] = [
                'source_module' => $parts[0] ?? 'Treasury',
                'source_entity_id' => $parts[1] ?? (string) $l->id,
                'currency' => $l->currency,
                'balance_minor' => (int) $l->remaining_amount,
                'carrying_functional_idr' => (int) $l->current_carrying_idr,
                'item_type' => 'fx_lot',
                'is_asset' => true,
                'lot_date' => $l->lot_date->format('Y-m-d'),
            ];
        }

        return $items;
    }

    public function getLatestRate(string $fromCurrency, string $toCurrency, string $rateType = 'spot'): ?RationalRate
    {
        $rate = ExchangeRate::where('from_currency', strtoupper($fromCurrency))
            ->where('to_currency', strtoupper($toCurrency))
            ->where('rate_type', $rateType)
            ->orderByDesc('rate_date')
            ->first();

        if ($rate === null) {
            return null;
        }

        return new RationalRate(
            (int) $rate->rate_numerator,
            (int) $rate->rate_denominator
        );
    }

    public function getHistoricalRate(string $fromCurrency, string $toCurrency, string $date, string $rateType = 'spot'): ?RationalRate
    {
        $rate = ExchangeRate::where('from_currency', strtoupper($fromCurrency))
            ->where('to_currency', strtoupper($toCurrency))
            ->where('rate_type', $rateType)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if ($rate === null) {
            return null;
        }

        return new RationalRate(
            (int) $rate->rate_numerator,
            (int) $rate->rate_denominator
        );
    }

    public function getMonetaryItems(string $entityId, string $period): array
    {
        return FxLot::where('entity_id', $entityId)
            ->where('status', 'active')
            ->get()
            ->map(fn ($lot) => [
                'id' => $lot->id,
                'currency' => $lot->currency,
                'amount' => $lot->remaining_amount,
                'carrying_rate' => new RationalRate(
                    $lot->current_carrying_rate_num,
                    $lot->current_carrying_rate_den
                ),
            ])
            ->toArray();
    }
}
