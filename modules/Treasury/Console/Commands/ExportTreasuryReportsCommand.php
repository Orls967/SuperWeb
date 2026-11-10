<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Contracts\ClockInterface;
use Modules\Treasury\Application\Reports\BankReconciliationReportExporter;
use Modules\Treasury\Application\Reports\CashForecast13WeekReportExporter;
use Modules\Treasury\Application\Reports\CashPoolSweepsReportExporter;
use Modules\Treasury\Application\Reports\CreditFacilityCovenantReportExporter;
use Modules\Treasury\Application\Reports\FxLotSubledgerReportExporter;
use Modules\Treasury\Application\Reports\FxPositionReportExporter;
use Modules\Treasury\Application\Reports\FxRevaluationReportExporter;
use Modules\Treasury\Application\Reports\HedgePortfolioReportExporter;

/**
 * Command untuk mengekspor 8 jenis laporan resmi Treasury dalam format CSV.
 */
final class ExportTreasuryReportsCommand extends Command
{
    protected $signature = 'treasury:export-reports
                            {--type=all : Tipe laporan (all, fx-positions, fx-lots, revaluations, hedges, facilities, cash-pools, reconciliation, forecast)}
                            {--out= : Direktori tujuan penyimpanan berkas CSV (default: storage/app/treasury_reports)}
                            {--currency= : Filter mata uang tertentu}
                            {--period= : Filter periode YYYY-MM}';

    protected $description = 'Mengekspor laporan resmi perbendaharaan (Treasury) ke berkas CSV';

    public function handle(
        ClockInterface $clock,
        FxPositionReportExporter $fxPosExporter,
        FxLotSubledgerReportExporter $fxLotExporter,
        FxRevaluationReportExporter $revalExporter,
        HedgePortfolioReportExporter $hedgeExporter,
        CreditFacilityCovenantReportExporter $facilityExporter,
        CashPoolSweepsReportExporter $poolExporter,
        BankReconciliationReportExporter $reconExporter,
        CashForecast13WeekReportExporter $forecastExporter
    ): int {
        $type = (string) $this->option('type');
        $outDir = (string) ($this->option('out') ?: storage_path('app/treasury_reports'));
        $currency = $this->option('currency') ? strtoupper((string) $this->option('currency')) : null;
        $period = $this->option('period') ? (string) $this->option('period') : null;

        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $nowStr = $clock->now()->format('Ymd_His');
        $this->info("=== Treasury Report Exporter [{$nowStr}] ===");

        $todayStr = $clock->now()->toDateString();
        $currPeriod = $period ?: $clock->now()->format('Y-m');
        $defaultBankId = \Modules\Treasury\Domain\Models\BankAccount::first()?->id ?? '';

        $reports = [
            'fx-positions' => [
                'name' => 'Posisi Valuta Asing (PSAK 10)',
                'file' => "{$outDir}/fx_positions_{$nowStr}.csv",
                'exporter' => fn () => $fxPosExporter->export($todayStr)['content'],
            ],
            'fx-lots' => [
                'name' => 'Subledger Lot Valas (FIFO)',
                'file' => "{$outDir}/fx_lots_{$nowStr}.csv",
                'exporter' => fn () => $fxLotExporter->export($currency)['content'],
            ],
            'revaluations' => [
                'name' => 'Jurnal Revaluasi Valas Akhir Bulan',
                'file' => "{$outDir}/fx_revaluations_{$nowStr}.csv",
                'exporter' => fn () => $revalExporter->export($currPeriod)['content'],
            ],
            'hedges' => [
                'name' => 'Portofolio Kontrak Lindung Nilai Forward',
                'file' => "{$outDir}/hedge_portfolio_{$nowStr}.csv",
                'exporter' => fn () => $hedgeExporter->export()['content'],
            ],
            'facilities' => [
                'name' => 'Fasilitas Kredit & Kepatuhan Covenant DER',
                'file' => "{$outDir}/credit_facilities_{$nowStr}.csv",
                'exporter' => fn () => $facilityExporter->export()['content'],
            ],
            'cash-pools' => [
                'name' => 'Riwayat Penyapuan Kas Notional Pooling',
                'file' => "{$outDir}/cash_pool_sweeps_{$nowStr}.csv",
                'exporter' => fn () => $poolExporter->export()['content'],
            ],
            'reconciliation' => [
                'name' => 'Hasil Rekonsiliasi Mutasi Rekening Koran',
                'file' => "{$outDir}/bank_reconciliation_{$nowStr}.csv",
                'exporter' => fn () => $reconExporter->export($defaultBankId)['content'],
            ],
            'forecast' => [
                'name' => 'Peramalan Arus Kas 13-Minggu',
                'file' => "{$outDir}/cash_forecast_13w_{$nowStr}.csv",
                'exporter' => fn () => $forecastExporter->export('base')['content'],
            ],
        ];

        $targets = ($type === 'all') ? array_keys($reports) : [$type];

        foreach ($targets as $key) {
            if (! isset($reports[$key])) {
                $this->error("Tipe laporan '{$key}' tidak dikenal.");
                continue;
            }

            $def = $reports[$key];
            $this->line("Mengekspor: {$def['name']}...");
            $csvContent = ($def['exporter'])();
            file_put_contents($def['file'], $csvContent);
            $size = strlen($csvContent);
            $this->info("✓ Selesai -> {$def['file']} ({$size} bytes)");
        }

        $this->info('Seluruh ekspor laporan Treasury berhasil diselesaikan.');

        return self::SUCCESS;
    }
}
