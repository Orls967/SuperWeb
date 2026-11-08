<?php

declare(strict_types=1);

namespace Modules\Aviation\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Aviation\Domain\Models\AviationRefund;
use Modules\Aviation\Ledger\AviationLedgerPostingService;

class RefundSettleCommand extends Command
{
    protected $signature = 'aviation:refund-settle {date : Tanggal mutasi refund YYYY-MM-DD}';

    protected $description = 'Menyelesaikan mutasi refund tiket harian ke buku besar secara idempoten';

    public function handle(AviationLedgerPostingService $ledgerService): int
    {
        $dateStr = $this->argument('date');
        $date = CarbonImmutable::parse($dateStr);

        $refunds = AviationRefund::whereDate('created_at', $date->toDateString())->get();
        $this->info("Menyelesaikan {$refunds->count()} refund untuk tanggal {$dateStr}");

        $settled = 0;
        foreach ($refunds as $ref) {
            $ledgerKey = "avi:refund:settle:{$ref->id}:{$dateStr}";
            $ledgerService->postTicketRefund($ref, $ledgerKey);
            $settled++;
        }

        $this->info("Berhasil menjurnal {$settled} mutasi refund ke buku besar.");

        return self::SUCCESS;
    }
}

