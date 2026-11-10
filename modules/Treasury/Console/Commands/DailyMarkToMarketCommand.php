<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Treasury\Application\Actions\DailyMarkToMarketAction;
use Modules\Treasury\Domain\Enums\HedgeStatus;
use Modules\Treasury\Domain\Models\ExchangeRate;
use Modules\Treasury\Domain\Models\ForwardContract;

class DailyMarkToMarketCommand extends Command
{
    protected $signature = 'treasury:mtm {--rate= : Override kurs spot scaled 1e4}';

    protected $description = 'Kalkulasi harian nilai wajar pasar (Mark-to-Market) seluruh kontrak lindung nilai aktif';

    public function handle(DailyMarkToMarketAction $action): int
    {
        $contracts = ForwardContract::whereIn('status', [HedgeStatus::ACTIVE->value, HedgeStatus::BOOKED->value])->get();

        if ($contracts->isEmpty()) {
            $this->info('Tidak ada kontrak forward aktif untuk evaluasi Mark-to-Market.');

            return self::SUCCESS;
        }

        $this->info("Menghitung Mark-to-Market untuk {$contracts->count()} kontrak aktif...");

        $totalMtm = 0;
        foreach ($contracts as $fwd) {
            $spotRateScaled = 15_800_000_000; // default 15,800
            if ($this->option('rate')) {
                $spotRateScaled = (int) $this->option('rate');
            } else {
                $rate = ExchangeRate::where('from_currency', $fwd->currency)
                    ->where('to_currency', 'IDR')
                    ->orderByDesc('rate_date')
                    ->first();
                if ($rate !== null) {
                    $spotRateScaled = (int) intdiv($rate->rate_numerator * 10000, $rate->rate_denominator);
                }
            }

            $mtm = $action->execute($fwd, $spotRateScaled);
            $totalMtm += $mtm;

            $this->line(" [FWD] {$fwd->contract_number}: MTM = Rp ".number_format($mtm));
        }

        $this->info('Total Nilai Wajar Posisi Derivatif MTM: Rp '.number_format($totalMtm));

        return self::SUCCESS;
    }
}
