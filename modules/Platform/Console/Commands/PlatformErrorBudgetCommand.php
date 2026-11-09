<?php

declare(strict_types=1);

namespace Modules\Platform\Console\Commands;

use Illuminate\Console\Command;
use Modules\Platform\Domain\Actions\CalculateErrorBudgetAction;
use Modules\Platform\Domain\Models\ServiceSlo;

class PlatformErrorBudgetCommand extends Command
{
    protected $signature = 'platform:error-budget';

    protected $description = 'Menghitung sisa error budget dan burn rate seluruh layanan pilar';

    public function handle(CalculateErrorBudgetAction $calculator): int
    {
        $slos = ServiceSlo::all();
        if ($slos->isEmpty()) {
            $this->info('Belum ada data SLO terdaftar.');

            return 0;
        }

        $rows = [];
        foreach ($slos as $slo) {
            $res = $calculator->calculate($slo);
            $rows[] = [
                $res['service'],
                "{$res['allowed_downtime_mins']}m",
                $res['burn_rate'],
                "{$res['budget_remaining_percent']}%",
                strtoupper($res['status']),
            ];
        }

        $this->table(['Service', 'Monthly Budget', 'Burn Rate (1h)', 'Budget Remaining', 'Status'], $rows);

        return 0;
    }
}
