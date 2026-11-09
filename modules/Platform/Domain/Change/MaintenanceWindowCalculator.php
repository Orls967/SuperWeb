<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use DateTimeImmutable;

final class MaintenanceWindowCalculator
{
    /**
     * Standard quiet operational hours (UTC) per business vertical.
     *
     * @var array<string, array{start_hour: int, end_hour: int, allowed_days: list<int>}>
     */
    private const WINDOW_PREFERENCES = [
        'Banking' => ['start_hour' => 19, 'end_hour' => 22, 'allowed_days' => [2, 3, 4]], // Tue-Thu off clearing
        'Payment' => ['start_hour' => 18, 'end_hour' => 21, 'allowed_days' => [2, 3]],
        'Mall' => ['start_hour' => 18, 'end_hour' => 23, 'allowed_days' => [1, 2, 3]], // Mon-Wed
        'Logistics' => ['start_hour' => 12, 'end_hour' => 16, 'allowed_days' => [6, 7]], // Weekend mid-day
        'Hospital' => ['start_hour' => 20, 'end_hour' => 23, 'allowed_days' => [2, 3, 4]],
    ];

    /**
     * Checks if a target deployment timestamp falls within the authorized quiet window.
     */
    public function isWithinWindow(string $module, DateTimeImmutable $targetTime): bool
    {
        $prefs = self::WINDOW_PREFERENCES[$module] ?? ['start_hour' => 18, 'end_hour' => 23, 'allowed_days' => [1, 2, 3, 4]];

        $dayOfWeek = (int) $targetTime->format('N'); // 1 (Mon) .. 7 (Sun)
        $hour = (int) $targetTime->format('G'); // 0 .. 23

        if (! in_array($dayOfWeek, $prefs['allowed_days'], true)) {
            return false;
        }

        return $hour >= $prefs['start_hour'] && $hour <= $prefs['end_hour'];
    }

    /**
     * Finds the next valid window after a reference time.
     */
    public function getNextValidWindow(string $module, DateTimeImmutable $fromTime): DateTimeImmutable
    {
        $prefs = self::WINDOW_PREFERENCES[$module] ?? ['start_hour' => 18, 'end_hour' => 23, 'allowed_days' => [1, 2, 3, 4]];

        $candidate = $fromTime;
        for ($i = 0; $i < 14; $i++) {
            $dayOfWeek = (int) $candidate->format('N');
            if (in_array($dayOfWeek, $prefs['allowed_days'], true)) {
                $windowCandidate = $candidate->setTime($prefs['start_hour'], 0, 0);
                if ($windowCandidate > $fromTime) {
                    return $windowCandidate;
                }
            }
            $candidate = $candidate->modify('+1 day');
        }

        return $fromTime->modify('+1 day')->setTime(18, 0, 0);
    }
}
