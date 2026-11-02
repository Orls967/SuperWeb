<?php

declare(strict_types=1);

namespace Modules\Med\Console\Commands;

use Illuminate\Console\Command;
use Modules\Med\Application\Services\RoyaltyWaterfallService;
use Modules\Med\Domain\Models\MedLicenseRevenue;

class SettleMediaRoyaltiesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'med:settle-royalties {--period= : Billing period YYYY-MM}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Settle media IP licensing royalties for the specified period';

    /**
     * Execute the console command.
     */
    public function handle(RoyaltyWaterfallService $waterfallService): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $this->info("Settling royalties for period: {$period}");

        $revenues = MedLicenseRevenue::where('billing_period', $period)
            ->where('status', 'PENDING')
            ->get();

        if ($revenues->isEmpty()) {
            $this->info("No pending revenues to settle for period {$period}.");

            return Command::SUCCESS;
        }

        $settledCount = 0;
        foreach ($revenues as $revenue) {
            $statement = $waterfallService->settleRoyalty([
                'license_id' => $revenue->license_id,
                'billing_period' => $period,
                'gross_revenue_minor' => (int) $revenue->gross_amount_minor,
                'distribution_fee_bps' => (int) $revenue->distribution_fee_bps,
                'idempotency_key' => "STMT:AUTO:{$revenue->id}:{$period}",
            ]);

            $revenue->update(['status' => 'SETTLED']);
            $settledCount++;
            $this->line("Settled license {$revenue->license_id}: Statement {$statement->statement_number}");
        }

        $this->info("Successfully settled {$settledCount} royalty statement(s).");

        return Command::SUCCESS;
    }
}
