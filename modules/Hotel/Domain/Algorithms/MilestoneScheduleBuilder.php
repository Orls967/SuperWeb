<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Algorithms;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * H17: MilestoneScheduleBuilder
 * Penjadwalan termin pembayaran DP/Termin I/Termin II/Pelunasan wedding & konferensi
 * dengan pembuktian matematis konservasi nilai kontrak tanpa selisih pembulatan rupiah.
 */
final class MilestoneScheduleBuilder
{
    /**
     * @param  array<int, float>|null  $customMilestoneRatios
     */
    public function build(
        string $contractId,
        int $totalContractAmountIdr,
        string $signingDate,
        string $eventStartDate,
        ?array $customMilestoneRatios = null,
        string $secretKey = 'superweb-hotel-mice-salt'
    ): MilestoneScheduleCertificate {
        if ($totalContractAmountIdr <= 0) {
            throw new InvalidArgumentException('Total contract amount must be greater than zero.');
        }

        $signDt = new DateTimeImmutable($signingDate);
        $eventDt = new DateTimeImmutable($eventStartDate);

        if ($eventDt <= $signDt) {
            throw new InvalidArgumentException('Event start date must be after signing date.');
        }

        $daysSpan = (int) $eventDt->diff($signDt)->format('%a');
        if ($daysSpan < 7) {
            throw new InvalidArgumentException('Event start date must be at least 7 days after signing date.');
        }

        // Default ratios: DP 20%, Termin I 30%, Termin II 30%, Pelunasan 20%
        $ratios = $customMilestoneRatios ?? [0.20, 0.30, 0.30, 0.20];
        if (count($ratios) !== 4) {
            throw new InvalidArgumentException('Exactly 4 milestone ratios required.');
        }

        // Termin 1: DP (due: signing + 7 days, max event - 1)
        $m1Due = $signDt->modify('+7 days');
        if ($m1Due >= $eventDt) {
            $m1Due = $eventDt->modify('-1 day');
        }
        $m1Amount = (int) floor($ratios[0] * $totalContractAmountIdr);

        // Termin 2: Mid-term booking (due: max(sign + 14, event - 90))
        $m2DueCandidate = $eventDt->modify('-90 days');
        $m2DueMin = $signDt->modify('+14 days');
        $m2Due = ($m2DueCandidate > $m2DueMin) ? $m2DueCandidate : $m2DueMin;
        if ($m2Due >= $eventDt) {
            $m2Due = $eventDt->modify('-3 days');
        }
        $m2Amount = (int) floor($ratios[1] * $totalContractAmountIdr);

        // Termin 3: Pre-event final guarantee (due: max(sign + 21, event - 30))
        $m3DueCandidate = $eventDt->modify('-30 days');
        $m3DueMin = $signDt->modify('+21 days');
        $m3Due = ($m3DueCandidate > $m3DueMin) ? $m3DueCandidate : $m3DueMin;
        if ($m3Due >= $eventDt) {
            $m3Due = $eventDt->modify('-2 days');
        }
        $m3Amount = (int) floor($ratios[2] * $totalContractAmountIdr);

        // Termin 4: Final Settlement (Pelunasan 20% + rounding residual cents)
        $m4Due = $eventDt->modify('-7 days');
        if ($m4Due <= $m3Due) {
            $m4Due = $eventDt->modify('-1 day');
        }
        $m4Amount = $totalContractAmountIdr - ($m1Amount + $m2Amount + $m3Amount);

        $milestones = [
            [
                'milestone_index' => 1,
                'name' => 'Deposit Konfirmasi (DP 20%)',
                'percentage' => round($ratios[0] * 100.0, 2),
                'amount_idr' => $m1Amount,
                'due_date' => $m1Due->format('Y-m-d'),
            ],
            [
                'milestone_index' => 2,
                'name' => 'Termin I (Perencanaan & Vendor Lock 30%)',
                'percentage' => round($ratios[1] * 100.0, 2),
                'amount_idr' => $m2Amount,
                'due_date' => $m2Due->format('Y-m-d'),
            ],
            [
                'milestone_index' => 3,
                'name' => 'Termin II (Finalisasi Menu & Rooming List 30%)',
                'percentage' => round($ratios[2] * 100.0, 2),
                'amount_idr' => $m3Amount,
                'due_date' => $m3Due->format('Y-m-d'),
            ],
            [
                'milestone_index' => 4,
                'name' => 'Pelunasan Akhir (Final Balance 20%)',
                'percentage' => round(($m4Amount / $totalContractAmountIdr) * 100.0, 2),
                'amount_idr' => $m4Amount,
                'due_date' => $m4Due->format('Y-m-d'),
            ],
        ];

        $sumAmounts = $m1Amount + $m2Amount + $m3Amount + $m4Amount;
        $isConserved = ($sumAmounts === $totalContractAmountIdr);

        $payload = implode('|', [
            $contractId,
            $totalContractAmountIdr,
            $signingDate,
            $eventStartDate,
            $sumAmounts,
            $isConserved ? '1' : '0',
            json_encode($milestones, JSON_UNESCAPED_SLASHES),
        ]);

        $signatureHash = hash_hmac('sha256', $payload, $secretKey);

        return new MilestoneScheduleCertificate(
            contractId: $contractId,
            totalContractAmountIdr: $totalContractAmountIdr,
            signingDate: $signingDate,
            eventStartDate: $eventStartDate,
            totalMilestonesSumIdr: $sumAmounts,
            isConserved: $isConserved,
            milestones: $milestones,
            signatureHash: $signatureHash
        );
    }
}
