<?php

declare(strict_types=1);

namespace Modules\Finance\Application\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Modules\Finance\Domain\Models\Loan;

/**
 * Hitung jadwal cicilan pembiayaan dengan bunga flat.
 */
class LoanSimulator
{
    public const ALLOWED_TENORS = [6, 12, 24, 36];

    /**
     * @return array{
     *     principal: int,
     *     tenor_months: int,
     *     interest_rate_annual: float,
     *     total_interest: int,
     *     total_payable: int,
     *     monthly_amount: int,
     *     schedule: array<int, array{sequence: int, due_date: string, principal_part: int, interest_part: int, amount: int}>
     * }
     */
    public function simulate(
        int $principal,
        int $tenorMonths,
        float $annualRate = 0.08,
        ?CarbonImmutable $startDate = null,
    ): array {
        if ($principal <= 0) {
            throw new InvalidArgumentException('Pokok pembiayaan harus lebih besar dari 0.');
        }

        if (! in_array($tenorMonths, self::ALLOWED_TENORS, true)) {
            $allowed = implode(', ', self::ALLOWED_TENORS);
            throw new InvalidArgumentException("Tenor hanya tersedia untuk {$allowed} bulan.");
        }

        $totalInterest = (int) round($principal * $annualRate * $tenorMonths / 12);
        $start = $startDate ?? CarbonImmutable::now();

        $principalPer = intdiv($principal, $tenorMonths);
        $interestPer = intdiv($totalInterest, $tenorMonths);

        // Sisa pembagian dibebankan pada cicilan terakhir agar total persis
        $principalRemainder = $principal - ($principalPer * $tenorMonths);
        $interestRemainder = $totalInterest - ($interestPer * $tenorMonths);

        $schedule = [];

        for ($i = 1; $i <= $tenorMonths; $i++) {
            $principalPart = $principalPer + ($i === $tenorMonths ? $principalRemainder : 0);
            $interestPart = $interestPer + ($i === $tenorMonths ? $interestRemainder : 0);

            $schedule[] = [
                'sequence' => $i,
                'due_date' => $start->addMonthsNoOverflow($i)->toDateString(),
                'principal_part' => $principalPart,
                'interest_part' => $interestPart,
                'amount' => $principalPart + $interestPart,
            ];
        }

        return [
            'principal' => $principal,
            'tenor_months' => $tenorMonths,
            'interest_rate_annual' => $annualRate,
            'total_interest' => $totalInterest,
            'total_payable' => $principal + $totalInterest,
            'monthly_amount' => $schedule[0]['amount'],
            'schedule' => $schedule,
        ];
    }

    /**
     * Kolateral minimal (dalam satuan aset) agar LTV pembukaan ≤ 50%.
     */
    public function requiredCollateral(int $principal, BigDecimal $price, int $decimals = 18): BigDecimal
    {
        return Loan::requiredCollateralQty($principal, $price, $decimals);
    }

    /**
     * LTV pembukaan untuk kolateral tertentu.
     */
    public function ltvFor(int $principal, BigDecimal $collateralQty, BigDecimal $price): float
    {
        $value = $collateralQty->multipliedBy($price);

        if ($value->isZero() || $value->isNegative()) {
            return 1.0;
        }

        return (float) (string) BigDecimal::of((string) $principal)
            ->dividedBy($value, 6, RoundingMode::HalfUp);
    }
}
