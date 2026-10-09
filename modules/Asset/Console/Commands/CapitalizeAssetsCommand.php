<?php

declare(strict_types=1);

namespace Modules\Asset\Console\Commands;

use Illuminate\Console\Command;
use Modules\Asset\Application\Services\AssetService;
use Modules\Asset\Domain\Models\Asset;

/**
 * Kapitalisasi idempoten seluruh aset non-legacy ke ledger
 * (idempotency key `ast:acquire:{id}` — aman dijalankan berulang).
 */
class CapitalizeAssetsCommand extends Command
{
    protected $signature = 'ast:capitalise';

    protected $description = 'Naikkan aset non-legacy yang belum tercatat ke ast:fixed_assets (idempoten)';

    public function handle(AssetService $service): int
    {
        $count = 0;

        foreach (Asset::query()
            ->where('source_type', '!=', 'legacy_backfill')
            ->cursor() as $asset) {
            if ($service->ensureCapitalized($asset)) {
                $count++;
                $this->line('  [✓] '.$asset->asset_number);
            }
        }

        $this->info("✓ ast:capitalise selesai: {$count} aset dinaikkan ke ledger (0 berarti semua sudah tercatat).");

        return self::SUCCESS;
    }
}
