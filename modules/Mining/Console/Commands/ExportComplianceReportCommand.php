<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Domain\Models\MiningReconciliationRun;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;

class ExportComplianceReportCommand extends Command
{
    protected $signature = 'mining:export-compliance-csv {--output= : Path file output CSV}';

    protected $description = 'Ekspor rekapitulasi data kepatuhan royalti, rekonsiliasi grade, dan audit minerba ke format CSV';

    public function handle(): int
    {
        $this->info('Mempersiapkan ekspor laporan kepatuhan minerba...');

        $outputPath = $this->option('output') ?: storage_path('app/compliance_mining_report.csv');

        $returns = MiningRoyaltyReturn::with('site')->latest()->get();
        $runs = MiningReconciliationRun::with('site')->latest()->get();

        $fp = fopen($outputPath, 'w');
        if (! $fp) {
            $this->error("Gagal membuka file tujuan: {$outputPath}");

            return 1;
        }

        // Header CSV
        fputcsv($fp, [
            'Tipe Entitas',
            'Nomor Referensi',
            'Kode Site',
            'Periode',
            'Tonase Produksi / Feed',
            'Tarif / Status',
            'Kewajiban Royalti (IDR)',
            'Status Siklus',
            'Tanggal Dibuat',
        ]);

        foreach ($returns as $ret) {
            fputcsv($fp, [
                'SPT_ROYALTI_M4',
                (string) $ret->return_number,
                (string) ($ret->site?->site_code ?? 'SITE-DEFAULT'),
                (string) $ret->period_month,
                (string) $ret->verified_volume_tons,
                (($ret->rate_bps ?? 0) / 100).'%',
                (string) $ret->calculated_royalty_idr,
                (string) ($ret->status->value ?? $ret->status),
                (string) $ret->created_at,
            ]);
        }

        foreach ($runs as $r) {
            fputcsv($fp, [
                'NERACA_MASSA_M5',
                (string) $r->run_number,
                (string) ($r->site?->site_code ?? 'SITE-DEFAULT'),
                (string) $r->period_month,
                (string) $r->total_ore_mined_tons,
                "Diskrepansi: {$r->variance_tons} Ton ({$r->variance_bps} bps)",
                '0',
                (string) ($r->status->value ?? $r->status),
                (string) $r->created_at,
            ]);
        }

        fclose($fp);

        $this->info("Laporan kepatuhan minerba berhasil diekspor ke: {$outputPath}");

        return 0;
    }
}
