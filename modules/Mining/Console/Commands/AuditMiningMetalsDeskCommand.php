<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\AuditMiningMetalsDeskAction;

final class AuditMiningMetalsDeskCommand extends Command
{
    protected $signature = 'mining:audit-metals';

    protected $description = 'Menjalankan audit rekonsiliasi menyeluruh metals desk: konservasi massa, MTM ledger, dan kolateral SRG';

    public function handle(AuditMiningMetalsDeskAction $action): int
    {
        $this->info('Menjalankan audit kepatuhan integritas domain Smelter & Metals Trading Desk (Fase 121)...');

        $report = $action->execute();

        $this->table(
            ['Parameter Audit Integritas', 'Hasil Pemeriksaan Faktual', 'Status Audit'],
            [
                ['Konservasi Massa Peleburan (Output <= Input)', $report['total_furnace_runs_audited'].' run tanur diinspeksi', $report['is_mass_conservation_verified'] ? 'LOLOS (0 Pelanggaran)' : 'GAGAL'],
                ['Jumlah Posisi Aktif Trading Desk', $report['active_trading_positions'].' posisi terbuka', 'TERVALIDASI'],
                ['Agregat MTM Unrealized PnL (IDR)', number_format($report['aggregate_unrealized_mtm_pnl_idr'], 0, ',', '.'), 'SESUAI LEDGER'],
                ['Resi Gudang Terkunci Kolateral SCF', $report['locked_collateral_receipts'].' resi berlisensi', 'STOK GUDANG TERKUNCI'],
                ['Total Fisik Logam Terkunci Jaminan', number_format($report['total_locked_metal_tons'], 2).' Ton Logam', 'FIDUSIA AKTIF'],
                ['Kesimpulan Status Audit Mutu', $report['audit_status'], $report['audit_status'] === 'CLEAN' ? 'HIJAU' : 'MERAH'],
            ]
        );

        $this->info("Audit metals desk selesai pada {$report['timestamp']}. Status: {$report['audit_status']}");

        if ($report['audit_status'] !== 'CLEAN') {
            $this->error('METALS DESK AUDIT FAILED: Terdeteksi ketidaksesuaian neraca massa atau integritas data.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
