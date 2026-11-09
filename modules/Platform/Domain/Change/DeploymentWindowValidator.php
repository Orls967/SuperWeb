<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use DateTimeImmutable;

final class DeploymentWindowValidator
{
    /**
     * Verifies that the proposed window does not collide with blackout dates (e.g. Black Friday, end of month reconciliation).
     *
     * @var list<array{start: string, end: string, reason: string}>
     */
    private const BLACKOUT_WINDOWS = [
        ['start' => '2026-11-25', 'end' => '2026-11-30', 'reason' => 'Black Friday / Cyber Monday E-Commerce Freeze'],
        ['start' => '2026-12-24', 'end' => '2027-01-02', 'reason' => 'Year-End Global Financial Settlement Freeze'],
    ];

    /**
     * Evaluates whether a proposed deployment date falls into any enterprise freeze blackout period.
     */
    public function isBlackout(DateTimeImmutable $date): array
    {
        $dateStr = $date->format('Y-m-d');

        foreach (self::BLACKOUT_WINDOWS as $w) {
            if ($dateStr >= $w['start'] && $dateStr <= $w['end']) {
                return [
                    'is_blackout' => true,
                    'reason' => $w['reason'],
                ];
            }
        }

        return [
            'is_blackout' => false,
            'reason' => 'Tanggal berada di luar jendela freeze blackout.',
        ];
    }
}
