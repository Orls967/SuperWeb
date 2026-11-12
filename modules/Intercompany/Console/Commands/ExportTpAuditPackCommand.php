<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Illuminate\Console\Command;
use Modules\Intercompany\Application\Services\GroupTransferPricingAuditPackExporter;

class ExportTpAuditPackCommand extends Command
{
    protected $signature = 'group:tp-audit-pack {--entity= : Kode entitas anak yang diuji} {--year= : Tahun pajak format YYYY} {--rule= : Kode aturan harga transfer}';

    protected $description = 'Menghasilkan paket dossier audit kepatuhan transfer pricing lengkap (PMK 172/2023)';

    public function handle(GroupTransferPricingAuditPackExporter $exporter): int
    {
        $entityCode = $this->option('entity') ?: 'ENT_MANUF_01';
        $year = $this->option('year') ?: '2026';
        $ruleCode = $this->option('rule') ?: 'TP-MFG-001';

        $this->info("Menyusun paket audit transfer pricing untuk entitas [{$entityCode}] tahun pajak [{$year}]...");

        $config = [
            'entity_code' => $entityCode,
            'fiscal_year' => $year,
            'rule_code' => $ruleCode,
        ];

        // Metrik keuangan pihak teruji
        $financialMetrics = [
            'revenue_minor' => 1_500_000_000_00,
            'cogs_minor' => 1_100_000_000_00,
            'opex_minor' => 250_000_000_00,
            'operating_profit_minor' => 150_000_000_00, // 10.00% Operating Margin
            'accounts_receivable_minor' => 180_000_000_00,
            'inventory_minor' => 120_000_000_00,
            'accounts_payable_minor' => 90_000_000_00,
        ];

        // Data pembanding independen (Comparables Dataset)
        $comparators = [
            [
                'name' => 'PT Manufaktur Presisi Mandiri',
                'revenue_minor' => 2_000_000_000_00,
                'cogs_minor' => 1_480_000_000_00,
                'opex_minor' => 340_000_000_00,
                'operating_profit_minor' => 180_000_000_00, // 9.00%
                'accounts_receivable_minor' => 220_000_000_00,
                'inventory_minor' => 150_000_000_00,
                'accounts_payable_minor' => 110_000_000_00,
            ],
            [
                'name' => 'PT Komponen Industri Perkasa',
                'revenue_minor' => 1_800_000_000_00,
                'cogs_minor' => 1_314_000_000_00,
                'opex_minor' => 306_000_000_00,
                'operating_profit_minor' => 180_000_000_00, // 10.00%
                'accounts_receivable_minor' => 190_000_000_00,
                'inventory_minor' => 130_000_000_00,
                'accounts_payable_minor' => 95_000_000_00,
            ],
            [
                'name' => 'PT Logam Teknik Otomotif',
                'revenue_minor' => 2_400_000_000_00,
                'cogs_minor' => 1_728_000_000_00,
                'opex_minor' => 408_000_000_00,
                'operating_profit_minor' => 264_000_000_00, // 11.00%
                'accounts_receivable_minor' => 260_000_000_00,
                'inventory_minor' => 180_000_000_00,
                'accounts_payable_minor' => 130_000_000_00,
            ],
        ];

        $pack = $exporter->exportAuditPack($config, $financialMetrics, $comparators);

        $this->info("Paket audit berhasil dibuat: ID [{$pack['audit_pack_id']}]");
        $this->line("Wajib Pajak: {$pack['legal_name']} (NPWP: {$pack['tax_id_npwp']})");
        $this->line("Kesimpulan: {$pack['local_file_dossier']['compliance_conclusion']}");
        $this->line("Hash Integritas: {$pack['audit_dossier_hash']}");

        $recon = $pack['tax_reconciliation_summary'];
        $this->table(
            ['Pos Rekonsiliasi Fiskal', 'Nilai'],
            [
                ['Laba Usaha Komersial', 'Rp '.number_format((float) $recon['commercial_profit_minor'], 0, ',', '.')],
                ['Penyesuaian Positif True-Up', 'Rp '.number_format((float) $recon['fiscal_positive_adjustment_minor'], 0, ',', '.')],
                ['Penyesuaian Negatif True-Up', 'Rp '.number_format((float) $recon['fiscal_negative_adjustment_minor'], 0, ',', '.')],
                ['Penghasilan Kena Pajak (PKP)', 'Rp '.number_format((float) $recon['taxable_income_minor'], 0, ',', '.')],
                ['Estimasi Beban PPh Badan (22%)', 'Rp '.number_format((float) $recon['corporate_tax_liability_minor'], 0, ',', '.')],
            ]
        );

        return self::SUCCESS;
    }
}
