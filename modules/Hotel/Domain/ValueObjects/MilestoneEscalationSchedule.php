<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final class MilestoneEscalationSchedule
{
    /**
     * @param  array<int, array{label: string, percent: float, days_before: int}>  $milestoneSteps
     */
    public function __construct(
        public readonly array $milestoneSteps = [
            ['label' => 'Booking Deposit', 'percent' => 20.0, 'days_before' => 180],
            ['label' => 'Second Installment', 'percent' => 30.0, 'days_before' => 90],
            ['label' => 'Third Installment', 'percent' => 30.0, 'days_before' => 30],
            ['label' => 'Final Settlement', 'percent' => 20.0, 'days_before' => 7],
        ]
    ) {
        $totalPercent = 0.0;
        foreach ($this->milestoneSteps as $step) {
            $totalPercent += (float) ($step['percent'] ?? 0.0);
        }
        if (abs($totalPercent - 100.0) > 0.001) {
            throw new InvalidArgumentException('Milestone percentages must sum up exactly to 100%');
        }
    }

    /**
     * @return array<int, array{label: string, percent: float, due_date: string, amount: float}>
     */
    public function generateSchedule(int $totalContractAmount, DateTimeImmutable $eventDate): array
    {
        $result = [];
        foreach ($this->milestoneSteps as $step) {
            $days = (int) ($step['days_before'] ?? 0);
            $dueDate = $eventDate->modify("-{$days} days")->format('Y-m-d');
            $pct = (float) ($step['percent'] ?? 0.0);
            $amount = round($totalContractAmount * ($pct / 100.0), 2);

            $result[] = [
                'label' => (string) ($step['label'] ?? 'Installment'),
                'percent' => $pct,
                'due_date' => $dueDate,
                'amount' => $amount,
            ];
        }

        return $result;
    }
}
