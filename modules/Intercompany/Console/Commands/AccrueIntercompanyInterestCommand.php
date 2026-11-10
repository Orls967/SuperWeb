<?php

declare(strict_types=1);

namespace Modules\Intercompany\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intercompany\Application\Actions\AccrueAndPayIntercompanyInterestAction;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyLoanSchedule;
use Psr\Clock\ClockInterface;

class AccrueIntercompanyInterestCommand extends Command
{
    protected $signature = 'ic:accrue-interest {--period= : Periode jatuh tempo format YYYY-MM} {--dry-run : Simulasi tanpa posting ledger}';

    protected $description = 'Menghitung akrual bunga pinjaman intercompany, memotong PPh 23/26, dan memosting jurnal buku besar';

    public function handle(
        AccrueAndPayIntercompanyInterestAction $action,
        ?ClockInterface $clock = null
    ): int {
        $now = $clock !== null ? CarbonImmutable::instance($clock->now()) : CarbonImmutable::now();
        $period = $this->option('period') ?: $now->format('Y-m');
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Menjalankan akrual bunga pinjaman intercompany untuk periode {$period}...");

        $schedules = IntercompanyLoanSchedule::where('due_date', 'like', "{$period}%")
            ->where('status', 'pending')
            ->get();

        if ($schedules->isEmpty()) {
            $this->warn("Tidak ada jadwal angsuran pinjaman berstatus pending pada periode {$period}.");

            return self::SUCCESS;
        }

        $processedCount = 0;
        $totalGrossInterest = 0;
        $totalWht = 0;

        foreach ($schedules as $schedule) {
            $loan = IntercompanyLoan::find($schedule->loan_id);
            if (! $loan) {
                continue;
            }

            $this->line("Memproses Jadwal Angsuran #{$schedule->period_number} Pinjaman [{$loan->loan_number}] {$loan->lender_entity} -> {$loan->borrower_entity}");
            $this->line('  Pokok Angsuran: Rp '.number_format((float) $schedule->principal_component_idr, 0, ',', '.'));
            $this->line('  Bunga Kotor: Rp '.number_format((float) $schedule->gross_interest_amount_idr, 0, ',', '.'));
            $this->line('  PPh 23 Dipotong: Rp '.number_format((float) $schedule->withholding_tax_amount_idr, 0, ',', '.'));

            if (! $dryRun) {
                $action->execute([
                    'schedule_id' => $schedule->id,
                    'operator_id' => null,
                ]);
            }

            $processedCount++;
            $totalGrossInterest += (int) $schedule->gross_interest_amount_idr;
            $totalWht += (int) $schedule->withholding_tax_amount_idr;
        }

        $this->info("Selesai memproses {$processedCount} jadwal pinjaman.");
        $this->line('Total Bunga Kotor: Rp '.number_format((float) $totalGrossInterest, 0, ',', '.'));
        $this->line('Total PPh 23 Dipotong: Rp '.number_format((float) $totalWht, 0, ',', '.'));

        if ($dryRun) {
            $this->comment('[DRY-RUN] Tidak ada data atau jurnal ledger yang disimpan.');
        }

        return self::SUCCESS;
    }
}
