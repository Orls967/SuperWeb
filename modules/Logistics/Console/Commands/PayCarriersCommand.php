<?php

declare(strict_types=1);

namespace Modules\Logistics\Console\Commands;

use Illuminate\Console\Command;
use Modules\Logistics\Application\Actions\PayCarriersAction;
use Modules\Logistics\Domain\Models\Carrier;

class PayCarriersCommand extends Command
{
    protected $signature = 'lgx:pay-carriers';

    protected $description = 'Bayar utang carrier subkontrak atas leg yang sudah diakrual dan melewati termin (jadwal mingguan)';

    public function handle(PayCarriersAction $action): int
    {
        $paid = 0;
        $total = 0;

        foreach (Carrier::orderBy('id')->get() as $carrier) {
            $payment = $action->execute($carrier);
            if ($payment) {
                $paid++;
                $total += $payment->amount_idr;
                $this->line("  {$carrier->code}: {$payment->leg_count} leg, Rp ".number_format($payment->amount_idr, 0, ',', '.'));
            }
        }

        $this->info("Pembayaran carrier: {$paid} carrier dibayar, total Rp ".number_format($total, 0, ',', '.').'.');

        return self::SUCCESS;
    }
}
