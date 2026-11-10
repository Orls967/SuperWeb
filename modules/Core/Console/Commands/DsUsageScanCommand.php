<?php

declare(strict_types=1);

namespace Modules\Core\Console\Commands;

use Illuminate\Console\Command;
use Modules\Core\Domain\Models\DsComponentUsage;
use Modules\Shared\Application\Ui\ComponentUsageScanner;

final class DsUsageScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ds:usage:scan
        {--json : Keluaran dalam format JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pemindai frekuensi pemakaian komponen x-ds.* di seluruh template Blade aplikasi';

    public function handle(ComponentUsageScanner $scanner): int
    {
        $asJson = (bool) $this->option('json');

        $result = $scanner->scan();

        // Simpan hasil pemindaian ke tabel core_ds_component_usage
        foreach ($result['usages'] as $compName => $views) {
            foreach ($views as $viewPath => $count) {
                $module = 'Core';
                if (preg_match('#modules/([^/]+)/#', $viewPath, $modMatch)) {
                    $module = $modMatch[1];
                }

                DsComponentUsage::updateOrCreate(
                    [
                        'component_name' => $compName,
                        'view_path' => $viewPath,
                    ],
                    [
                        'module' => $module,
                        'usage_count' => $count,
                        'has_deprecated_props' => false,
                        'last_scanned_at' => now(),
                        'last_detected_at' => now(),
                    ]
                );
            }
        }

        $usages = DsComponentUsage::all();

        if ($asJson) {
            $this->line((string) json_encode([
                'total_views_scanned' => $result['total_views'],
                'components_found' => $result['components_found'],
                'total_occurrences' => $result['occurrences'],
                'items' => $usages->toArray(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return 0;
        }

        $this->info(sprintf(
            'Pemindaian selesai: %d file Blade dipindai, %d komponen unik ditemukan dengan %d pemakaian total.',
            $result['total_views'],
            $result['components_found'],
            $result['occurrences']
        ));

        if ($usages->isNotEmpty()) {
            $this->table(
                ['Komponen', 'View Path', 'Jumlah Pemakaian', 'Terakhir Dipindai'],
                $usages->take(30)->map(fn ($u) => [
                    $u->component_name,
                    $u->view_path,
                    $u->usage_count,
                    (string) $u->last_scanned_at,
                ])->toArray()
            );
        }

        return 0;
    }
}
