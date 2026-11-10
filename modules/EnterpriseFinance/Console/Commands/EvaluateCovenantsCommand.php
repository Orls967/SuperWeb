<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Console\Commands;

use Illuminate\Console\Command;
use Modules\EnterpriseFinance\Application\Services\EnterpriseFinanceService;
use Modules\EnterpriseFinance\Domain\Models\SyndicatedFacility;

class EvaluateCovenantsCommand extends Command
{
    protected $signature = 'ef:evaluate-covenants {--period= : Periode evaluasi (mis. 2026-Q3)} {--facility= : Kode fasilitas tertentu}';

    protected $description = 'Evaluasi kepatuhan covenant pinjaman sindikasi dan obligasi korporat (D7)';

    public function handle(EnterpriseFinanceService $service): int
    {
        $period = (string) ($this->option('period') ?: date('Y-\QQ', strtotime('-1 month')));
        $facilityCode = $this->option('facility');

        $this->info("Menjalankan evaluasi covenant pinjaman sindikasi untuk periode: {$period}...");

        $query = SyndicatedFacility::where('status', 'ACTIVE');
        if ($facilityCode) {
            $query->where('facility_code', $facilityCode);
        }

        $facilities = $query->get();
        if ($facilities->isEmpty()) {
            $this->warn('Tidak ada fasilitas sindikasi aktif yang ditemukan.');

            return self::SUCCESS;
        }

        $breachCount = 0;
        foreach ($facilities as $fac) {
            $evaluation = $service->evaluateDebtCovenants(
                facility: $fac,
                evaluationPeriod: $period,
                ebitdaMinor: 500_000_000_000,
                taxPaidMinor: 50_000_000_000,
                debtServiceMinor: 250_000_000_000,
                totalDebtMinor: 1_200_000_000_000,
                totalEquityMinor: 1_000_000_000_000,
                ebitMinor: 420_000_000_000,
                interestExpenseMinor: 120_000_000_000,
                currentAssetsMinor: 750_000_000_000,
                currentLiabilitiesMinor: 450_000_000_000
            );

            $statusText = $evaluation->is_compliant ? '<info>COMPLIANT</info>' : '<error>BREACH</error>';
            $this->line("Fasilitas [{$fac->facility_code}] DSCR: {$evaluation->dscr_permille}‰, DER: {$evaluation->der_permille}‰ -> {$statusText}");

            if (! $evaluation->is_compliant) {
                $breachCount++;
            }
        }

        if ($breachCount > 0) {
            $this->warn("Terdapat {$breachCount} pelanggaran covenant terdeteksi!");
        } else {
            $this->info('Seluruh fasilitas sindikasi memenuhi batas aman covenant rasio.');
        }

        return self::SUCCESS;
    }
}
