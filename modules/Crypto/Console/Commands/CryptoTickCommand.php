<?php

declare(strict_types=1);

namespace Modules\Crypto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Crypto\Application\Services\PriceEngineService;

class CryptoTickCommand extends Command
{
    protected $signature = 'crypto:tick';

    protected $description = 'Simulate real-time crypto price ticks via geometric random walk';

    public function handle(PriceEngineService $priceEngine): int
    {
        $this->info('Menjalankan simulasi tick harga kripto...');

        $updatedPrices = $priceEngine->tick();

        foreach ($updatedPrices as $symbol => $price) {
            $formatted = number_format((float) $price, 0, ',', '.');
            $this->line("  [{$symbol}] => Rp {$formatted}");
        }

        $this->info('✓ Tick harga berhasil diperbarui.');

        return self::SUCCESS;
    }
}
