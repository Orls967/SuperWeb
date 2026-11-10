<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use DateTimeInterface;

/**
 * Kalkulator penuaan dan penyusutan prioritas tugas (Priority Time-Decay Calculator).
 */
class TaskPriorityDecayCalculator
{
    /**
     * Menghitung faktor pengali penuaan (aging multiplier) berdasarkan waktu tunggu antrean.
     */
    public function calculateAgingMultiplier(DateTimeInterface $createdAt, ?DateTimeInterface $currentTime = null): float
    {
        $now = $currentTime ? $currentTime->getTimestamp() : time();
        $created = $createdAt->getTimestamp();
        $waitingHours = max(0.0, ($now - $created) / 3600.0);

        // Setiap 24 jam tugas tertunda, berikan penambahan urgensi progresif 10% (maksimal 2.0x)
        $factor = 1.0 + ($waitingHours / 240.0);

        return round(min(2.0, $factor), 4);
    }
}
