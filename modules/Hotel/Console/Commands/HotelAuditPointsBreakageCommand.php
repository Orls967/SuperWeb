<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\RecognizeLoyaltyBreakageRevenueAction;

class HotelAuditPointsBreakageCommand extends Command
{
    protected $signature = 'hotel:audit-points-breakage {--dry-run} {--pass-id=}';

    protected $description = 'Audit batch poin yang melewati masa kedaluwarsa 730 hari dan akui pendapatan breakage (IFRS 15)';

    public function handle(RecognizeLoyaltyBreakageRevenueAction $action): int
    {
        $passId = $this->option('pass-id');
        $this->info('Memulai audit poin kedaluwarsa IFRS 15...');

        $result = $action->execute($passId ? (string) $passId : null);

        $this->line("Total Batch Dipindai: {$result->totalBatchesScanned}");
        $this->line("Batch Kedaluwarsa: {$result->totalExpiredBatches}");
        $this->line("Poin Hangus: {$result->totalExpiredPts} PTS");
        $this->info('Pendapatan Breakage Diakui: Rp '.number_format($result->breakageRevenueIdr, 0, ',', '.'));

        return Command::SUCCESS;
    }
}
