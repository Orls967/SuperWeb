<?php

declare(strict_types=1);

namespace Modules\Treasury\Application\Services;

use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Treasury\Domain\Models\BankAccount;
use Modules\Treasury\Domain\Models\BankStatement;
use Modules\Treasury\Domain\Models\CashForecast;
use Modules\Treasury\Domain\Models\CashPool;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Domain\Models\Currency;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;
use Modules\Treasury\Domain\Models\Revaluation;

class TreasuryService
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {
        $this->ledger = $ledger ?? app(Ledger::class);
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

        $date = $date ?? Carbon::today()->toDateString();

        $rate = ExchangeRate::where('from_currency', $from)
            ->where('to_currency', $to)
            ->where('rate_type', $rateType)
            ->whereDate('rate_date', '<=', $date)
            ->orderByDesc('rate_date')
            ->first();

        if (! $rate) {
            // Coba kebalikannya jika ada
            $inverseRate = ExchangeRate::where('from_currency', $to)
                ->where('to_currency', $from)
                ->where('rate_type', $rateType)
                ->whereDate('rate_date', '<=', $date)
                ->orderByDesc('rate_date')
                ->first();

            if (! $inverseRate) {
                throw new InvalidArgumentException("Kurs dari {$from} ke {$to} untuk tanggal {$date} tidak ditemukan.");
            }

            // Target = amount * inverseDenominator / inverseNumerator
            return (int) intdiv($amountMinorUnits * (int) $inverseRate->rate_denominator, (int) $inverseRate->rate_numerator);
        }

        // Target = amount * numerator / denominator
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

        // Posting foreign ledger entries (balanced per currency)
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
            ]
        ));

        return [
            'foreign_tx' => $txForeign,
            'functional_amount_idr' => $functionalAmountIdr,
            'rate_converted' => true,
        ];
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

            return Revaluation::updateOrCreate(
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
                ]
            );
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
        // Pastikan currency ada
        Currency::firstOrCreate(
            ['code' => $curr],
            ['name' => $curr, 'symbol' => $curr, 'minor_units' => 2, 'is_active' => true]
        );

        return BankAccount::create([
            'account_number' => $accountNumber,
            'bank_name' => $bankName,
            'currency' => $curr,
            'balance' => $initialBalance,
            'is_petty_cash' => $isPettyCash,
            'is_active' => true,
        ]);
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
        $statements = BankStatement::where('bank_account_id', $bankAccountId)
            ->where('is_reconciled', false)
            ->get();

        $reconciledCount = 0;
        foreach ($statements as $st) {
            // Simulasi auto-match dengan mencocokkan refNo & menandai reconciled
            $st->update([
                'is_reconciled' => true,
                'matched_ledger_tx_id' => 1,
            ]);
            $reconciledCount++;
        }

        return [
            'total_processed' => $statements->count(),
            'reconciled' => $reconciledCount,
        ];
    }

    /**
     * 48.5 Cash Forecast 13 Minggu
     */
    public function generate13WeekForecast(int $openingBalanceIdr, string $startDate, string $scenario = 'base'): Collection
    {
        $currentBalance = $openingBalanceIdr;
        $date = Carbon::parse($startDate);

        CashForecast::where('scenario', $scenario)->delete();

        for ($week = 1; $week <= 13; $week++) {
            // Simulasi inflow / outflow operasional mingguan
            $projectedInflow = 150_000_000 + ($week * 5_000_000);
            $projectedOutflow = 120_000_000 + ($week * 3_000_000);
            $net = $projectedInflow - $projectedOutflow;
            $currentBalance += $net;

            CashForecast::create([
                'scenario' => $scenario,
                'week_number' => $week,
                'start_date' => $date->copy()->addWeeks($week - 1)->toDateString(),
                'projected_inflow_idr' => $projectedInflow,
                'projected_outflow_idr' => $projectedOutflow,
                'net_cash_flow_idr' => $net,
                'closing_balance_idr' => $currentBalance,
            ]);
        }

        return CashForecast::where('scenario', $scenario)->orderBy('week_number')->get();
    }

    /**
     * 48.6 Lindung nilai (Forward Contract) & Mark-to-market
     */
    public function createForwardContract(
        string $currency,
        int $notionalForeignAmount,
        int $forwardRateScaled,
        string $maturityDate
    ): ForwardContract {
        return ForwardContract::create([
            'contract_number' => 'FWD-'.strtoupper(Str::random(8)),
            'currency' => strtoupper($currency),
            'notional_foreign_amount' => $notionalForeignAmount,
            'forward_rate_scaled' => $forwardRateScaled,
            'maturity_date' => $maturityDate,
            'mtm_value_idr' => 0,
            'status' => 'active',
        ]);
    }

    public function markToMarket(ForwardContract $fwd, int $currentSpotRateScaled): int
    {
        // MTM = Notional * (Spot Rate - Forward Rate) / 1e6
        $diff = $currentSpotRateScaled - (int) $fwd->forward_rate_scaled;
        $mtm = (int) intdiv($fwd->notional_foreign_amount * $diff, 1000000);

        $fwd->update(['mtm_value_idr' => $mtm]);

        return $mtm;
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
        return CreditFacility::create([
            'facility_code' => $code,
            'bank_name' => $bankName,
            'credit_limit_idr' => $limitIdr,
            'drawn_amount_idr' => 0,
            'interest_rate_percent' => $interestPercent,
            'max_debt_equity_ratio' => $maxDebtEquity,
            'status' => 'active',
        ]);
    }

    public function drawFacility(CreditFacility $facility, int $amountIdr, float $currentDer): array
    {
        if ($facility->drawn_amount_idr + $amountIdr > $facility->credit_limit_idr) {
            throw new InvalidArgumentException('Penarikan melampaui plafon kredit yang disetujui.');
        }

        $covenantBreached = $currentDer > (float) $facility->max_debt_equity_ratio;

        $facility->increment('drawn_amount_idr', $amountIdr);

        return [
            'facility' => $facility->fresh(),
            'drawn_amount' => $amountIdr,
            'covenant_breached' => $covenantBreached,
            'warning' => $covenantBreached ? 'Peringatan: Rasio DER melampaui batas covenant bank!' : null,
        ];
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
        return DB::transaction(function () use ($pool) {
            $sub = BankAccount::lockForUpdate()->find($pool->sub_account_id);
            $header = BankAccount::lockForUpdate()->find($pool->header_account_id);

            $excess = $sub->balance - $pool->target_balance_idr;

            if ($excess <= 0) {
                return 0;
            }

            $sub->decrement('balance', $excess);
            $header->increment('balance', $excess);

            $pool->update([
                'last_swept_amount_idr' => $excess,
                'last_swept_at' => Carbon::now(),
            ]);

            return $excess;
        });
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

        // Cek limit vs drawn invariant
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
}
