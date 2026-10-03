<?php

declare(strict_types=1);

namespace Modules\Resto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Resto\Application\Actions\DiscardTrayAction;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;

class ExpireDisplayTraysCommand extends Command
{
    protected $signature = 'resto:expire-display {--outlet= : ID outlet spesifik}';

    protected $description = 'Memeriksa dan membuang piring etalase yang kedaluwarsa (melewati batas waktu 6 jam) ke waste';

    public function handle(DiscardTrayAction $discardAction): int
    {
        $this->info('Memeriksa piring etalase yang kedaluwarsa...');

        $query = DisplayTray::whereIn('status', [
            TrayStatus::ON_DISPLAY,
            TrayStatus::IN_SERVICE,
            TrayStatus::RETURNED,
        ])->where('expires_at', '<=', now());

        if ($outletId = $this->option('outlet')) {
            $query->where('outlet_id', $outletId);
        }

        $totalWasteValue = 0;
        $count = 0;

        // Proses per batch agar piring etalase skala besar tidak dimuat sekaligus ke memori.
        $query->chunkById(200, function ($expiredTrays) use ($discardAction, &$totalWasteValue, &$count): void {
            foreach ($expiredTrays as $tray) {
                $val = $tray->totalWasteValue();
                $discardAction->handle($tray, 'Kedaluwarsa otomatis batas pajang etalase (6 jam)');
                $totalWasteValue += $val;
                $count++;
            }
        });

        if ($count === 0) {
            $this->info('Tidak ada piring etalase yang kedaluwarsa.');

            return self::SUCCESS;
        }

        $this->info("Berhasil membuang {$count} piring etalase kedaluwarsa dengan total nilai waste: Rp ".number_format($totalWasteValue, 0, ',', '.'));

        return self::SUCCESS;
    }
}
