<?php

declare(strict_types=1);

namespace Modules\Asset\Console\Commands;

use Illuminate\Console\Command;
use Modules\Asset\Application\Services\AssetAuditService;
use Modules\Asset\Application\Services\AssetTcoService;
use Modules\Asset\Domain\Models\Asset;

/**
 * 31.8 Rekonsiliasi subledger aset ↔ ledger (harus 0 selisih) + rekomendasi TCO.
 */
class AuditAssetCommand extends Command
{
    protected $signature = 'ast:audit {--tco : Tampilkan TCO per aset + rekomendasi ganti}';

    protected $description = 'Audit aset: penyusutan, book value, ledger, hash-chain (0 selisih)';

    public function handle(AssetAuditService $audit, AssetTcoService $tco): int
    {
        $this->info('Memulai audit subledger aset...');

        $result = $audit->audit();
        $rows = [];

        foreach ($result['discrepancies'] as $d) {
            $rows[] = [$d['asset'], $d['kind'], number_format($d['expected']), number_format($d['actual']), number_format($d['difference']), 'SELISIH'];
        }

        $this->table(
            ['Aset', 'Pemeriksaan', 'Diharapkan', 'Aktual', 'Selisih', 'Status'],
            $rows === []
                ? [['—', 'Semua pemeriksaan', '0', '0', '0', 'OK']]
                : $rows
        );

        if ((bool) $this->option('tco')) {
            $this->line('TCO per aset (susut + pemeliharaan + asuransi + BBM):');
            $tcoRows = [];

            foreach (Asset::query()->where('status', '!=', 'disposed')->cursor() as $asset) {
                $r = $tco->tco($asset);
                if ($r['total_tco_idr'] > 0 || $r['recommend_replace']) {
                    $tcoRows[] = [
                        $asset->asset_number,
                        number_format($r['depreciation_idr']),
                        number_format($r['maintenance_idr']),
                        number_format($r['insurance_idr']),
                        number_format($r['total_tco_idr']),
                        $r['tco_percent'].'%',
                        $r['recommend_replace'] ? 'GANTI' : '—',
                    ];
                }
            }

            if ($tcoRows !== []) {
                $this->table(['Aset', 'Susut', 'Pemeliharaan', 'Asuransi', 'TCO', '%', 'Rekomendasi'], $tcoRows);
            }
        }

        foreach ($result['health_pillars'] as $pillar => $state) {
            $state === 'ok'
                ? $this->line("  [✓] {$pillar}: OK")
                : $this->error("  [✗] {$pillar}: RUSAK");
        }

        if (! $result['balanced']) {
            $this->error('DITEMUKAN SELISIH PADA SUBLEDGER ASET.');

            return self::FAILURE;
        }

        $this->info("✓ ast:audit selesai: {$result['checked']} aset diperiksa, 0 selisih.");

        return self::SUCCESS;
    }
}
