<?php

declare(strict_types=1);

namespace Modules\Asset\Console\Commands;

use Illuminate\Console\Command;
use Modules\Asset\Application\Services\AssetService;

/**
 * Verifikasi integritas rantai hash riwayat aset (30.4).
 */
class VerifyAssetChainsCommand extends Command
{
    protected $signature = 'ast:verify-chain {--asset= : UUID aset spesifik}';

    protected $description = 'Verifikasi rantai hash SHA-256 seluruh riwayat aset (append-only)';

    public function handle(AssetService $service): int
    {
        $this->info('Memverifikasi rantai hash riwayat aset...');

        $result = $service->verifyChain($this->option('asset'));

        if ($result['valid']) {
            $this->info("✓ {$result['checked']} event aset terverifikasi: rantai hash utuh dan bebas manipulasi.");

            return self::SUCCESS;
        }

        $this->error("✗ Rantai rusak pada {$result['checked']} event diperiksa:");
        foreach ($result['broken'] as $item) {
            $this->error("  [✗] Aset {$item['asset']} seq {$item['sequence']}: {$item['message']}");
        }

        return self::FAILURE;
    }
}
