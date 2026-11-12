<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Services\ConsolidationEliminationRollupOrchestrator;
use Psr\Clock\ClockInterface;

class LockConsolidationCommand extends Command
{
    protected $signature = 'group:consolidation-lock {--period= : Periode fiskal yang dikunci format YYYY-MM} {--holding=HOLDING_CORP : Kode entitas induk holding}';

    protected $description = 'Mengeksekusi roll-up konsolidasi grup dan mengunci periode eliminasi intercompany';

    public function handle(
        ConsolidationEliminationRollupOrchestrator $orchestrator,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = $this->option('period') ?: $now->format('Y-m');
        $holding = strtoupper($this->option('holding') ?: 'HOLDING_CORP');

        $this->info("Menjalankan konsolidasi dan penguncian periode fiskal [{$period}] untuk induk [{$holding}]...");

        $config = [
            'fiscal_period' => $period,
            'holding_entity_code' => $holding,
            'operator_user_id' => 1,
            'presentation_currency' => 'IDR',
            'persist_eliminations' => true,
        ];

        // Neraca lajur gabungan dasar
        $trialBalances = [
            $holding => [
                'cash_minor' => 500_000_000_00,
                'ar_third_party_minor' => 300_000_000_00,
                'ar_intercompany_minor' => 200_000_000_00,
                'inventory_minor' => 150_000_000_00,
                'fixed_assets_minor' => 1_000_000_000_00,
                'ap_third_party_minor' => 250_000_000_00,
                'ap_intercompany_minor' => 0,
                'loans_intercompany_payable_minor' => 0,
                'loans_intercompany_receivable_minor' => 500_000_000_00,
                'equity_capital_minor' => 1_500_000_000_00,
                'retained_earnings_minor' => 600_000_000_00,
                'revenue_third_party_minor' => 800_000_000_00,
                'revenue_intercompany_minor' => 150_000_000_00,
                'cogs_third_party_minor' => 400_000_000_00,
                'cogs_intercompany_minor' => 0,
                'opex_minor' => 250_000_000_00,
                'currency' => 'IDR',
            ],
            'ENT_MANUF_01' => [
                'cash_minor' => 200_000_000_00,
                'ar_third_party_minor' => 150_000_000_00,
                'ar_intercompany_minor' => 0,
                'inventory_minor' => 350_000_000_00,
                'fixed_assets_minor' => 800_000_000_00,
                'ap_third_party_minor' => 180_000_000_00,
                'ap_intercompany_minor' => 200_000_000_00,
                'loans_intercompany_payable_minor' => 500_000_000_00,
                'loans_intercompany_receivable_minor' => 0,
                'equity_capital_minor' => 400_000_000_00,
                'retained_earnings_minor' => 120_000_000_00,
                'revenue_third_party_minor' => 500_000_000_00,
                'revenue_intercompany_minor' => 0,
                'cogs_third_party_minor' => 280_000_000_00,
                'cogs_intercompany_minor' => 150_000_000_00,
                'opex_minor' => 170_000_000_00,
                'currency' => 'IDR',
            ],
        ];

        $unrealizedProfits = [
            [
                'selling_entity' => $holding,
                'buying_entity' => 'ENT_MANUF_01',
                'unrealized_profit_minor' => 25_000_000_00,
                'sale_type' => 'DOWNSTREAM',
            ],
        ];

        $result = $orchestrator->runAndLock($config, $trialBalances, $unrealizedProfits);

        $this->info("Penguncian konsolidasi sukses: Sesi [{$result['consolidation_run_id']}]");
        $this->line("Waktu Kunci: {$result['locked_at']}");
        $this->line('Total Saldo Dieliminasi: Rp '.number_format((float) $result['total_eliminated_minor'], 0, ',', '.'));
        $this->line("Jurnal Tersimpan: {$result['persisted_entries_count']} baris");
        $this->line("Integritas Hash: {$result['audit_hash']}");

        $fin = $result['consolidated_financials'];
        $this->table(
            ['Pos Laporan Keuangan Konsolidasian', 'Nilai'],
            [
                ['Total Aset Konsolidasi', 'Rp '.number_format((float) $fin['consolidated_assets_minor'], 0, ',', '.')],
                ['Total Liabilitas Konsolidasi', 'Rp '.number_format((float) $fin['consolidated_liabilities_minor'], 0, ',', '.')],
                ['Total Ekuitas Konsolidasi', 'Rp '.number_format((float) $fin['consolidated_equity_minor'], 0, ',', '.')],
                ['Pendapatan Usaha Bersih', 'Rp '.number_format((float) $fin['consolidated_revenue_minor'], 0, ',', '.')],
                ['Laba Bersih Konsolidasian', 'Rp '.number_format((float) $fin['consolidated_net_profit_minor'], 0, ',', '.')],
                ['Bagian Laba Entitas Induk', 'Rp '.number_format((float) $fin['parent_share_net_profit_minor'], 0, ',', '.')],
                ['Bagian Laba Kepentingan Non-Pengendali (NCI)', 'Rp '.number_format((float) $fin['nci_share_net_profit_minor'], 0, ',', '.')],
            ]
        );

        return self::SUCCESS;
    }
}
