<?php

declare(strict_types=1);

namespace Modules\Syariah\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Syariah\Application\Services\BmtMicrofinanceService;
use Modules\Syariah\Domain\Models\SybBmtSavings;
use Modules\Syariah\Domain\Models\SybMicroFacility;
use Modules\Syariah\Domain\Models\SybMicroSchedule;

class BmtDailyWaterfallSweepCommand extends Command
{
    protected $signature = 'syb:bmt-waterfall-sweep';

    protected $description = 'Menyapu angsuran pembiayaan mikro jatuh tempo dari saldo tabungan BMT nasabah via algoritma waterfall';

    public function handle(BmtMicrofinanceService $bmtService): int
    {
        $today = CarbonImmutable::now()->toDateString();
        $dueSchedules = SybMicroSchedule::where('due_date', '<=', $today)
            ->where('status', 'PENDING')
            ->get();

        $processed = 0;
        foreach ($dueSchedules as $schedule) {
            $facility = SybMicroFacility::where('facility_code', $schedule->facility_code)->first();
            if (! $facility) {
                continue;
            }

            // Cek saldo tabungan wadiah nasabah
            $savings = SybBmtSavings::where('customer_party_id', $facility->customer_party_id)
                ->where('is_active', true)
                ->first();

            $totalDue = $schedule->principal_due_idr + $schedule->margin_due_idr;
            if ($savings && $savings->balance_idr >= $totalDue) {
                // Eksekusi auto-sweep
                $bmtService->withdrawSavings(
                    accountNumber: $savings->account_number,
                    amountIdr: $totalDue,
                    note: 'Auto-Debit Angsuran Pembiayaan '.$facility->facility_code
                );

                $bmtService->executeWaterfallRepayment(
                    facilityCode: $facility->facility_code,
                    payerPartyId: $facility->customer_party_id,
                    paymentAmountIdr: $totalDue,
                    dueMarginIdr: $schedule->margin_due_idr,
                    duePrincipalIdr: $schedule->principal_due_idr,
                    tazirRateBps: 0,
                    daysOverdue: 0
                );

                $schedule->status = 'PAID';
                $schedule->paid_at = CarbonImmutable::now();
                $schedule->save();

                $facility->outstanding_balance_idr -= $schedule->principal_due_idr;
                if ($facility->outstanding_balance_idr <= 0) {
                    $facility->outstanding_balance_idr = 0;
                    $facility->status = 'paid_off';
                }
                $facility->save();

                $processed++;
            }
        }

        $this->info("BMT waterfall sweep selesai. Total jadwal terbayar: {$processed}");

        return self::SUCCESS;
    }
}
