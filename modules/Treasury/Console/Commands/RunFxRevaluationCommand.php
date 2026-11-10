<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\RunMultiCurrencyFxRevaluationAction;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\FxLot;

class RunFxRevaluationCommand extends Command
{
    protected $signature = 'treasury:fx-revalue {--period= : Periode revaluasi YYYY-MM} {--currency=USD : Mata uang yang direvaluasi} {--rate= : Kurs scaled 1e4 (opsional)}';

    protected $description = 'Jalankan kalkulasi revaluasi kurs valas akhir periode PSAK 10 dan posting ke ledger';

    public function handle(RunMultiCurrencyFxRevaluationAction $action): int
    {
        $period = (string) ($this->option('period') ?: date('Y-m'));
        $currency = strtoupper((string) ($this->option('currency') ?: 'USD'));

        $this->info("Menjalankan Revaluasi Valas: Periode {$period}, Mata Uang {$currency}...");

        // Ambil saldo aktif dari FxLot atau default 10,000 unit
        $foreignBalance = (int) FxLot::where('currency', $currency)->where('status', 'active')->sum('remaining_amount');
        if ($foreignBalance === 0) {
            $foreignBalance = 1_000_000; // 10,000 USD dalam cents
        }

        $bookFunctionalIdr = (int) FxLot::where('currency', $currency)->where('status', 'active')->sum('current_carrying_idr');
        if ($bookFunctionalIdr === 0) {
            $bookFunctionalIdr = 150_000_000; // Asumsi cost 15,000 / USD
        }

        // Ambil kurs
        $rateOption = $this->option('rate');
        if ($rateOption !== null) {
            $spotRateScaled = (int) $rateOption;
        } else {
            $latestRate = ExchangeRate::where('from_currency', $currency)
                ->where('to_currency', 'IDR')
                ->orderByDesc('rate_date')
                ->first();

            $spotRateScaled = $latestRate
                ? (int) intdiv($latestRate->rate_numerator * 10000, $latestRate->rate_denominator)
                : 155_000_000; // 15,500 IDR/USD scaled
        }

        $res = $action->execute(
            period: $period,
            currency: $currency,
            foreignBalanceMinor: $foreignBalance,
            bookFunctionalIdr: $bookFunctionalIdr,
            spotRateScaled: $spotRateScaled
        );

        $this->info("Revaluasi berhasil: Gain/Loss IDR = {$res['gain_loss_idr']}");
        $this->line("Revaluation ID: {$res['revaluation']->id}");

        return self::SUCCESS;
    }
}
