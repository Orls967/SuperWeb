<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Syariah\Domain\Enums\IslamicTradeStatus;
use Modules\Syariah\Domain\Models\SybScfInvoice;
use Modules\Syariah\Domain\Models\SybTradeAuditLog;

final class ScfInvoiceDueSweepCommand extends Command
{
    protected $signature = 'syb:scf-invoice-due-sweep';

    protected $description = 'Pemeriksaan harian jatuh tempo tagihan SCF Murabahah dan monitoring keterlambatan';

    public function handle(): int
    {
        $today = CarbonImmutable::now();
        $this->info("Memulai sweep tagihan SCF murabahah per {$today->toDateString()}...");

        $invoices = SybScfInvoice::with('facility')
            ->where('status', IslamicTradeStatus::ACTIVE->value)
            ->where('due_date', '<=', $today->addDays(7)->toDateString())
            ->get();

        $processed = 0;
        $overdueCount = 0;

        foreach ($invoices as $inv) {
            $dueDate = CarbonImmutable::parse($inv->due_date);
            $totalPaid = (int) $inv->repayments()->sum('amount_paid_cents');
            $remaining = $inv->total_sale_price_cents - $totalPaid;

            if ($remaining <= 0) {
                $inv->update(['status' => IslamicTradeStatus::COMPLETED->value]);
                $this->info("Invoice {$inv->invoice_number} telah lunas penuh.");

                continue;
            }

            if ($dueDate->isPast()) {
                $overdueCount++;
                $daysOverdue = (int) $dueDate->diffInDays($today);
                $this->warn("Invoice SCF {$inv->invoice_number} jatuh tempo {$daysOverdue} hari lalu! Sisa tagihan: Rp ".number_format($remaining / 100, 2));

                SybTradeAuditLog::create([
                    'audit_ref' => $inv->invoice_number,
                    'event_name' => 'SCF_INVOICE_OVERDUE',
                    'details' => "Tagihan SCF murabahah terlambat {$daysOverdue} hari. Sisa kewajiban tangguh: Rp ".number_format($remaining / 100, 2),
                    'shariah_compliance_status' => 'WARNING',
                ]);
            }

            $processed++;
        }

        $this->info("Sweep SCF selesai. Diproses: {$processed} invoice, Menunggak: {$overdueCount}.");

        return self::SUCCESS;
    }
}
