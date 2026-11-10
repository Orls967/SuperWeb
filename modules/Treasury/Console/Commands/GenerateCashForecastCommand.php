<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\GenerateSeasonalCashForecastAction;
use Modules\Treasury\Domain\Models\BankAccount;

class GenerateCashForecastCommand extends Command
{
    protected $signature = 'treasury:forecast {--scenario=base : Skenario base, conservative, atau aggressive} {--start= : Tanggal mulai YYYY-MM-DD}';

    protected $description = 'Kalkulasi forecast arus kas 13 minggu dengan dekomposisi musiman D3';

    public function handle(GenerateSeasonalCashForecastAction $action): int
    {
        $scenario = (string) ($this->option('scenario') ?: 'base');
        $startDate = (string) ($this->option('start') ?: date('Y-m-d'));

        $this->info("Menghasilkan forecast arus kas 13 minggu (Skenario: {$scenario}, Mulai: {$startDate})...");

        $openingBalance = (int) BankAccount::sum('balance');
        if ($openingBalance === 0) {
            $openingBalance = 500_000_000; // Baseline
        }

        $historicalInflows = [
            140_000_000, 160_000_000, 150_000_000, 170_000_000,
            145_000_000, 165_000_000, 155_000_000, 175_000_000,
        ];

        $records = $action->execute($openingBalance, $startDate, $historicalInflows, $scenario, 13);

        $this->table(
            ['Minggu', 'Mulai', 'Inflow IDR', 'Outflow IDR', 'Net IDR', 'Saldo Akhir IDR'],
            $records->map(fn ($r) => [
                $r->week_number,
                $r->start_date->format('Y-m-d'),
                number_format($r->projected_inflow_idr),
                number_format($r->projected_outflow_idr),
                number_format($r->net_cash_flow_idr),
                number_format($r->closing_balance_idr),
            ])->toArray()
        );

        $this->info("Selesai menghasilkan {$records->count()} minggu forecast arus kas.");

        return self::SUCCESS;
    }
}
