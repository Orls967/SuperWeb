<?php

declare(strict_types=1);

namespace Modules\Hotel\Console\Commands;

use Illuminate\Console\Command;
use Modules\Hotel\Application\Actions\AuditMiceEventReconciliationAction;
use Modules\Hotel\Domain\Models\MiceContract;

final class HotelAuditMiceContractsCommand extends Command
{
    protected $signature = 'hotel:inspect-mice-contracts {--verify-invariants : Verifikasi invarian matematika}';

    protected $description = 'Audit rekonsiliasi finansial dan invarian kontrak MICE/Wedding dengan ledger pembukuan';

    public function handle(AuditMiceEventReconciliationAction $action): int
    {
        $this->info('Memulai audit rekonsiliasi kontrak MICE & Wedding...');

        $contracts = MiceContract::all();
        $balancedCount = 0;
        $unbalancedCount = 0;

        foreach ($contracts as $contract) {
            $report = $action->execute($contract);
            if ($report['is_balanced']) {
                $balancedCount++;
            } else {
                $unbalancedCount++;
                $this->warn("Kontrak {$contract->event_code} memiliki selisih: IDR ".number_format($report['outstanding_balance_idr']));
            }
        }

        $this->info("Audit selesai. Seimbang: {$balancedCount}, Belum Lunas/Selisih: {$unbalancedCount}.");

        return self::SUCCESS;
    }
}
