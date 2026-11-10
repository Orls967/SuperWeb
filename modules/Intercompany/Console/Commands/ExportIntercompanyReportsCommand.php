<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Reports\AssetAndStockTransfersReportExporter;
use Modules\Intercompany\Application\Reports\ConsolidatedSegmentReportExporter;
use Modules\Intercompany\Application\Reports\CostSharingSummaryReportExporter;
use Modules\Intercompany\Application\Reports\EliminationEntriesReportExporter;
use Modules\Intercompany\Application\Reports\IntercompanyLoanAmortizationReportExporter;
use Modules\Intercompany\Application\Reports\IntercompanyMatrixReportExporter;
use Modules\Intercompany\Application\Reports\NciDistributionReportExporter;
use Modules\Intercompany\Application\Reports\TransferPricingComplianceReportExporter;
use Psr\Clock\ClockInterface;

class ExportIntercompanyReportsCommand extends Command
{
    protected $signature = 'ic:export-reports {--type=all : Jenis laporan (all, matrix, tp, loans, eliminations, segments, nci, transfers, costsharing)} {--period= : Periode laporan (YYYY-MM)} {--output-dir= : Direktori penyimpanan CSV}';

    protected $description = 'Mengekspor 8 jenis laporan audit intercompany, transfer pricing, dan konsolidasi dalam format CSV berintegritas SHA-256';

    public function handle(?ClockInterface $clock = null): int
    {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = (string) ($this->option('period') ?: $now->format('Y-m'));
        $fiscalYear = (string) $now->year;
        $type = (string) $this->option('type');
        $outputDir = $this->option('output-dir') ? rtrim((string) $this->option('output-dir'), '/') : storage_path('reports/intercompany');

        if (! is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $this->info("Mengekspor laporan intercompany ke: {$outputDir}");

        $exporters = [
            'matrix' => fn () => (new IntercompanyMatrixReportExporter)->export($period),
            'tp' => fn () => (new TransferPricingComplianceReportExporter)->export($fiscalYear),
            'loans' => fn () => (new IntercompanyLoanAmortizationReportExporter)->export(),
            'eliminations' => fn () => (new EliminationEntriesReportExporter)->export($period),
            'segments' => fn () => (new ConsolidatedSegmentReportExporter)->export($period),
            'nci' => fn () => (new NciDistributionReportExporter)->export($fiscalYear),
            'transfers' => fn () => (new AssetAndStockTransfersReportExporter)->export($period),
            'costsharing' => fn () => (new CostSharingSummaryReportExporter)->export($period),
        ];

        $toRun = ($type === 'all') ? array_keys($exporters) : explode(',', $type);
        $exportedCount = 0;

        foreach ($toRun as $key) {
            $key = trim($key);
            if (! isset($exporters[$key])) {
                $this->warn("Tipe exporter '{$key}' tidak dikenal. Dilewati.");

                continue;
            }

            $res = $exporters[$key]();
            $filePath = "{$outputDir}/{$res['filename']}";
            file_put_contents($filePath, $res['content']);

            $this->line(" [OK] {$res['filename']} ({$res['row_count']} baris) SHA-256: ".substr($res['sha256'], 0, 16).'...');
            $exportedCount++;
        }

        $this->info("Ekspor selesai: {$exportedCount} berkas laporan berhasil dibuat.");

        return self::SUCCESS;
    }
}
