<?php

declare(strict_types=1);

namespace Modules\Mobility\Fleet\Application\Services;

use Modules\Mobility\Fleet\Domain\Models\FleetAmortizationSchedule;

class Psak73AmortizationEngine
{
    /**
     * @return list<array{contract_id: int, period_month: int, principal_idr: int, interest_idr: int, payment_idr: int, ending_liability_idr: int}>
     */
    public function generateSchedule(
        int $contractId,
        int $presentValueLiabilityIdr,
        int $monthlyPaymentIdr,
        int $durationMonths,
        int $annualRateBps
    ): array {
        $schedule = [];
        $currentLiability = $presentValueLiabilityIdr;
        $monthlyRate = $annualRateBps / 120000.0;

        for ($t = 1; $t <= $durationMonths; $t++) {
            if ($t < $durationMonths) {
                $interest = (int) round($currentLiability * $monthlyRate);
                $principal = $monthlyPaymentIdr - $interest;
                $currentLiability -= $principal;
            } else {
                // Bulan terakhir menyerap sisa agar liabilitas akhir persis 0
                $principal = $currentLiability;
                $interest = $monthlyPaymentIdr - $principal;
                $currentLiability = 0;
            }

            $schedule[] = [
                'contract_id' => $contractId,
                'period_month' => $t,
                'principal_idr' => $principal,
                'interest_idr' => $interest,
                'payment_idr' => $monthlyPaymentIdr,
                'ending_liability_idr' => $currentLiability,
            ];
        }

        return $schedule;
    }

    public function persistSchedule(
        int $contractId,
        int $presentValueLiabilityIdr,
        int $monthlyPaymentIdr,
        int $durationMonths,
        int $annualRateBps
    ): array {
        $rows = $this->generateSchedule(
            $contractId,
            $presentValueLiabilityIdr,
            $monthlyPaymentIdr,
            $durationMonths,
            $annualRateBps
        );

        foreach ($rows as $row) {
            FleetAmortizationSchedule::updateOrCreate(
                ['contract_id' => $contractId, 'period_month' => $row['period_month']],
                $row
            );
        }

        return $rows;
    }
}
