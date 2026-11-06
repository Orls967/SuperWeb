<?php

declare(strict_types=1);

namespace Modules\Egy\Console\Commands;

use Illuminate\Console\Command;
use Modules\Egy\Application\Actions\MatchCarbonOrdersAction;

final class EgyCarbonMatchCommand extends Command
{
    protected $signature = 'egy:carbon-match {--cycle=DAILY : Identifier siklus pencocokan}';

    protected $description = 'Jalankan pencocokan orderbook kredit karbon internal konglomerasi berbasis Price-Time Priority';

    public function handle(MatchCarbonOrdersAction $action): int
    {
        $cycle = (string) $this->option('cycle');
        $this->info("Menjalankan pencocokan orderbook kredit karbon siklus {$cycle}...");

        $trades = $action->execute($cycle);

        $this->info(sprintf('Pencocokan selesai: %d transaksi berhasil dieksekusi ke ledger.', count($trades)));

        foreach ($trades as $t) {
            $this->line(sprintf(
                '  [MATCH] Trade #%s | %d kg CO2e @ Rp %s/ton -> Rp %s (Tx #%d)',
                $t->trade_number,
                $t->matched_kg_co2e,
                number_format($t->execution_price_minor_per_ton, 0, ',', '.'),
                number_format($t->total_amount_idr, 0, ',', '.'),
                $t->ledger_transaction_id ?? 0
            ));
        }

        return self::SUCCESS;
    }
}
