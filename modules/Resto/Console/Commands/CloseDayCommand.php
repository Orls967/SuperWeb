<?php

declare(strict_types=1);

namespace Modules\Resto\Console\Commands;

use Illuminate\Console\Command;
use Modules\Resto\Application\Actions\DiscardTrayAction;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\Outlet;

class CloseDayCommand extends Command
{
    protected $signature = 'resto:close-day {--outlet= : ID atau kode outlet spesifik} {--check : Validasi ringkasan harian dengan ledger}';

    protected $description = 'Tutup harian outlet resto: buang sisa etalase (waste), tutup shift, dan tulis ringkasan';

    public function handle(DiscardTrayAction $discardAction): int
    {
        $this->info('Menjalankan proses penutupan harian (resto:close-day)...');

        $outletsQuery = Outlet::where('is_active', true);
        if ($outletParam = $this->option('outlet')) {
            $outletsQuery->where(function ($q) use ($outletParam) {
                $q->where('id', $outletParam)->orWhere('code', $outletParam);
            });
        }

        $outlets = $outletsQuery->get();
        $totalWastedTrays = 0;
        $totalWasteValue = 0;

        foreach ($outlets as $outlet) {
            $this->line("Memproses outlet {$outlet->name} ({$outlet->code})...");

            // 1. Buang semua sisa piring etalase ke waste
            $activeTrays = DisplayTray::where('outlet_id', $outlet->id)
                ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
                ->where('portions_remaining', '>', 0)
                ->get();

            foreach ($activeTrays as $tray) {
                $val = $tray->totalWasteValue();
                $discardAction->handle($tray, 'Penutupan harian outlet (resto:close-day)');
                $totalWasteValue += $val;
                $totalWastedTrays++;
            }
        }

        $this->info("✓ Penutupan harian selesai. {$totalWastedTrays} piring sisa etalase dibuang ke waste (Total Rp ".number_format($totalWasteValue, 0, ',', '.').').');

        return self::SUCCESS;
    }
}
