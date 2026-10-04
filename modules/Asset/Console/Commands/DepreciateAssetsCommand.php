<?php

declare(strict_types=1);

namespace Modules\Asset\Console\Commands;

use Illuminate\Console\Command;
use Modules\Asset\Application\Services\DepreciationService;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\Depreciation;

/**
 * 31.1 Penyusutan bulanan idempoten per (aset, periode).
 *
 * Buku komersial (default) & buku fiskal dihitung terpisah (31.2).
 */
class DepreciateAssetsCommand extends Command
{
    protected $signature = 'ast:depreciate
        {--period= : Periode YYYY-MM (default: bulan berjalan)}
        {--book=commercial : commercial|fiscal}
        {--asset= : UUID aset spesifik}';

    protected $description = 'Jalankan penyusutan aset (idempoten per aset/periode/buku)';

    public function handle(DepreciationService $service): int
    {
        $period = (string) ($this->option('period') ?: now()->format('Y-m'));
        $book = (string) $this->option('book');

        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            $this->error('Format periode harus YYYY-MM.');

            return self::FAILURE;
        }

        $query = Asset::query()->where('status', '!=', AssetStatus::Disposed);
        if ($assetId = $this->option('asset')) {
            $query->where('id', $assetId);
        }

        $total = 0;
        $count = 0;

        foreach ($query->cursor() as $asset) {
            try {
                $result = $service->depreciate($asset, $period, null, $book);
            } catch (\Throwable $e) {
                $this->warn("  [!] {$asset->asset_number}: {$e->getMessage()}");

                continue;
            }

            if ($result['amount_idr'] > 0) {
                $count++;
                $total += $result['amount_idr'];
                $this->line("  [✓] {$asset->asset_number} · {$period} · {$book} · ".number_format($result['amount_idr']).' IDR');
            }
        }

        $this->info(sprintf(
            '✓ Penyusutan %s periode %s: %d aset disusut, total %s IDR (%d rekaman tersedia).',
            $book,
            $period,
            $count,
            number_format($total),
            Depreciation::where('period', $period)->where('book', $book)->count(),
        ));

        return self::SUCCESS;
    }
}
