<?php

declare(strict_types=1);

namespace Modules\Treasury\Console\Commands;

use Illuminate\Console\Command;
use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Treasury\Domain\Models\CreditFacility;

class TestCreditCovenantCommand extends Command
{
    protected $signature = 'treasury:covenant-test {--facility= : Kode fasilitas kredit bank}';

    protected $description = 'Uji pemenuhan covenant Debt-to-Equity Ratio (DER) pada seluruh fasilitas kredit bank';

    public function handle(?LoanExposureProvider $provider = null): int
    {
        $code = $this->option('facility');
        $query = CreditFacility::where('status', 'active');
        if ($code) {
            $query->where('facility_code', $code);
        }

        $facilities = $query->get();
        if ($facilities->isEmpty()) {
            $this->info('Tidak ada fasilitas kredit bank aktif.');

            return self::SUCCESS;
        }

        $totalDebt = $provider ? $provider->getGroupTotalDebtIdr() : 0;
        $totalEquity = max(1, $provider ? $provider->getGroupTotalEquityIdr() : 1_000_000_000_000);

        $this->info("Total Utang Grup: Rp ".number_format($totalDebt));
        $this->info("Total Ekuitas Grup: Rp ".number_format($totalEquity));

        $hasBreach = false;
        foreach ($facilities as $fac) {
            $facDebt = $totalDebt + $fac->drawn_amount_idr;
            $derPermil = (int) intdiv($facDebt * 1000, $totalEquity);
            $maxDerPermil = (int) ($fac->max_der_permil ?: (int) round(((float) $fac->max_debt_equity_ratio) * 1000));

            $statusText = $derPermil <= $maxDerPermil ? 'PATUH' : 'BREACH';
            if ($derPermil > $maxDerPermil) {
                $hasBreach = true;
                $this->error(" [!] {$fac->facility_code}: DER {$derPermil}/1000 melampaui batas {$maxDerPermil}/1000 ({$statusText})");
            } else {
                $this->line(" [✓] {$fac->facility_code}: DER {$derPermil}/1000 <= {$maxDerPermil}/1000 ({$statusText})");
            }
        }

        return $hasBreach ? self::FAILURE : self::SUCCESS;
    }
}
