<?php

declare(strict_types=1);

namespace Modules\Mining\Console\Commands;

use Illuminate\Console\Command;
use Modules\Mining\Application\Actions\SettleShiftHaulingBillingAction;
use Modules\Mining\Domain\Models\MiningSite;

class ShiftCloseCommand extends Command
{
    protected $signature = 'mining:shift-close {date : Tanggal shift YYYY-MM-DD} {shift : Kode shift misal SHIFT_A}';

    protected $description = 'Menutup shift tambang, merekonsiliasi tonase, dan membukukan tagihan hauling kontraktor (idempoten)';

    public function handle(SettleShiftHaulingBillingAction $action): int
    {
        $date = (string) $this->argument('date');
        $shift = (string) $this->argument('shift');

        $this->info("Menutup shift operasional tambang: {$date} [{$shift}]...");

        $sites = MiningSite::all();
        if ($sites->isEmpty()) {
            $this->warn('Tidak ada site tambang terdaftar.');

            return 0;
        }

        foreach ($sites as $site) {
            $key = "min:shift-close:{$site->site_code}:{$date}:{$shift}";

            $result = $action->execute([
                'site_id' => (string) $site->id,
                'shift_date' => $date,
                'shift_code' => $shift,
                'contractor_party_id' => 'CONT-BORNEO-PRIMARY',
                'rate_per_ton_km_minor' => 1500,
                'idempotency_key' => $key,
            ]);

            $this->line("Site {$site->site_code}: Tagihan kontraktor Rp ".number_format($result['amount_minor']).' dibukukan ke ledger.');
        }

        $this->info('Penutupan shift berhasil diselesaikan.');

        return 0;
    }
}

