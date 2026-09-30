<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Mall\Domain\Enums\InvoiceLineType;
use Modules\Mall\Domain\Enums\InvoiceStatus;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Models\Invoice;
use Modules\Mall\Domain\Models\InvoiceLine;

class ApplyLatePenaltiesAction
{
    /**
     * Hitung denda keterlambatan harian untuk seluruh invoice yang jatuh tempo dan suspend jika > 30 hari.
     *
     * @return array{penalized_invoices: int, suspended_leases: int}
     */
    public function execute(?Carbon $now = null): array
    {
        $today = ($now ?? Carbon::now())->startOfDay();

        $invoices = Invoice::with(['lease', 'lines'])
            ->whereIn('status', [InvoiceStatus::ISSUED, InvoiceStatus::PARTIALLY_PAID, InvoiceStatus::OVERDUE])
            // whereDate agar perbandingan tanggal konsisten di MySQL maupun SQLite
            ->whereDate('due_date', '<', $today->toDateString())
            ->get();

        $penalizedCount = 0;
        $suspendedCount = 0;

        foreach ($invoices as $invoice) {
            $lease = $invoice->lease;
            if (! $lease) {
                continue;
            }

            $dueDate = Carbon::parse($invoice->due_date)->startOfDay();
            if (! $today->greaterThan($dueDate)) {
                continue;
            }

            $daysOverdue = abs((int) $today->diffInDays($dueDate));
            if ($daysOverdue <= 0) {
                continue;
            }

            DB::transaction(function () use ($invoice, $lease, $daysOverdue, &$penalizedCount, &$suspendedCount) {
                // Hitung sisa tagihan non-denda yang belum dibayar
                $unpaidSubtotal = 0;
                foreach ($invoice->lines as $line) {
                    if ($line->type !== InvoiceLineType::PENALTY) {
                        $unpaidSubtotal += $line->remainingAmount();
                    }
                }

                $dailyRatePercent = (float) $lease->penalty_rate_daily_percent;
                $dailyPenalty = (int) round($unpaidSubtotal * ($dailyRatePercent / 100.0));
                $totalPenalty = $dailyPenalty * $daysOverdue;

                // Cari baris denda yang sudah ada atau buat baru
                $penaltyLine = $invoice->lines()->where('type', InvoiceLineType::PENALTY)->first();

                if ($penaltyLine) {
                    $penaltyLine->update([
                        'description' => "Denda Keterlambatan {$daysOverdue} Hari ({$dailyRatePercent}%/hari)",
                        'quantity' => (float) $daysOverdue,
                        'unit_price' => $dailyPenalty,
                        'amount' => $totalPenalty,
                    ]);
                } else {
                    InvoiceLine::create([
                        'invoice_id' => $invoice->id,
                        'type' => InvoiceLineType::PENALTY,
                        'description' => "Denda Keterlambatan {$daysOverdue} Hari ({$dailyRatePercent}%/hari)",
                        'quantity' => (float) $daysOverdue,
                        'unit_price' => $dailyPenalty,
                        'amount' => $totalPenalty,
                        'paid_amount' => 0,
                        'status' => 'unpaid',
                    ]);
                }

                $invoice->update([
                    'penalty_amount' => $totalPenalty,
                    'total_amount' => $invoice->subtotal + $totalPenalty,
                    'status' => $invoice->status === InvoiceStatus::PARTIALLY_PAID ? InvoiceStatus::PARTIALLY_PAID : InvoiceStatus::OVERDUE,
                ]);

                $penalizedCount++;

                // Suspend lease jika keterlambatan melewati H+30 (lebih dari 30 hari)
                if ($daysOverdue > 30 && $lease->status === LeaseStatus::ACTIVE) {
                    $lease->update(['status' => LeaseStatus::SUSPENDED]);
                    $suspendedCount++;
                }
            });
        }

        return [
            'penalized_invoices' => $penalizedCount,
            'suspended_leases' => $suspendedCount,
        ];
    }
}
