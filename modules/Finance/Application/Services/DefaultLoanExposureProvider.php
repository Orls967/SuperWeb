<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Services;

use Modules\Finance\Contracts\LoanExposureProvider;
use Modules\Finance\Domain\Models\Loan;

final class DefaultLoanExposureProvider implements LoanExposureProvider
{
    public function totalLoanExposureMinor(string $asOfDate): int
    {
        // Total pokok pinjaman yang masih outstanding
        return (int) Loan::where('status', 'active')
            ->sum('principal_amount');
    }

    public function totalInterestExpenseMinor(string $period): int
    {
        // Estimasi bunga berjalan
        return (int) Loan::where('status', 'active')
            ->sum('interest_amount');
    }
}
