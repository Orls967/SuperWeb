<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\SettleCodAction;
use Modules\Logistics\Domain\Models\CodCollection;

class SettleCodCommand extends Command
{
    protected $signature = 'lgx:settle-cod {--days= : Override jumlah hari tunggu D+N (default config logistics.cod_settlement_days)}';

    protected $description = 'Cairkan dana COD yang sudah disetor ke hub (D+N) ke dompet shipper dikurangi fee COD';

    public function handle(SettleCodAction $action): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : (int) config('logistics.cod_settlement_days', 2);
        $cutoff = now()->subDays($days);

        $settled = 0;
        $net = 0;
        $fee = 0;

        CodCollection::where('status', CodCollection::STATUS_DEPOSITED)
            ->where('deposited_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($action, &$settled, &$net, &$fee) {
                foreach ($rows as $row) {
                    if ($action->execute($row)) {
                        $row->refresh();
                        $settled++;
                        $net += $row->net_amount_idr;
                        $fee += $row->fee_idr;
                    }
                }
            });

        $this->info("COD D+{$days} dicairkan: {$settled} resi; dana ke shipper Rp ".number_format($net, 0, ',', '.').'; fee Rp '.number_format($fee, 0, ',', '.').'.');

        return self::SUCCESS;
    }
}
