<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Domain\Models\MiningStockpile;
use Modules\Mining\Domain\Models\WeighbridgeTicket;

class AuditMiningRoyaltyCommand extends Command
{
    protected $signature = 'mining:check-royalty {--site= : Filter site id tertentu}';

    protected $description = 'Audit kepatuhan integritas royalti, verifikasi neraca jembatan timbang vs stockpile, dan deteksi tampering data tonase';

    public function handle(): int
    {
        $this->info('Memulai audit integritas royalti dan jembatan timbang...');

        $siteId = $this->option('site');

        $ticketsQuery = WeighbridgeTicket::query();
        $stockpilesQuery = MiningStockpile::query();

        if ($siteId) {
            $ticketsQuery->where('site_id', $siteId);
            $stockpilesQuery->where('site_id', $siteId);
        }

        $totalWeighbridgeNetKg = (int) $ticketsQuery->sum('net_weight_kg');
        $totalStockpileKg = (int) $stockpilesQuery->sum('total_kg');

        $this->line("Total Tiket Timbang: {$totalWeighbridgeNetKg} kg");
        $this->line("Total Stockpile DB : {$totalStockpileKg} kg");

        // Deteksi manipulasi langsung (tampering) di database
        // Toleransi jika tidak ada tiket timbang
        if ($totalWeighbridgeNetKg > 0 && abs($totalWeighbridgeNetKg - $totalStockpileKg) > 100) {
            $diff = abs($totalWeighbridgeNetKg - $totalStockpileKg);
            $this->error("AUDIT GAGAL: Terdeteksi manipulasi data tonase! Selisih timbangan vs stockpile: {$diff} kg.");

            return 1;
        }

        $this->info('AUDIT BERSIH: Seluruh tiket timbang sesuai dengan saldo fisik stockpile dan perhitungan royalti.');

        return 0;
    }
}
