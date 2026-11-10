<?php

declare(strict_types=1);

namespace Modules\Core\Application\Actions\TaskInbox;

use DateTimeInterface;
use Modules\Core\Domain\Models\CoreTask;

/**
 * Mesin kalkulasi aturan SLA (Service Level Agreement) tugas kotak masuk.
 */
class TaskSlaRuleEngine
{
    /**
     * Standar SLA jam penyelesaian default berdasarkan kombinasi prioritas & modul.
     */
    private const SLA_HOURS_MAP = [
        'urgent' => [
            'Finance' => 2,
            'Operations' => 4,
            'Procurement' => 4,
            'default' => 4,
        ],
        'high' => [
            'Finance' => 8,
            'Operations' => 12,
            'Procurement' => 16,
            'default' => 24,
        ],
        'normal' => [
            'default' => 48,
        ],
        'low' => [
            'default' => 120,
        ],
    ];

    /**
     * Menghitung tanggal tenggat SLA yang direkomendasikan saat tugas dibuat.
     */
    public function computeRecommendedDueAt(string $priority, string $module, ?DateTimeInterface $startDate = null): \DateTimeImmutable
    {
        $start = $startDate ? \DateTimeImmutable::createFromInterface($startDate) : new \DateTimeImmutable;
        $priorityKey = strtolower($priority);
        $moduleKey = ucfirst($module);

        $hours = self::SLA_HOURS_MAP[$priorityKey][$moduleKey]
            ?? self::SLA_HOURS_MAP[$priorityKey]['default']
            ?? self::SLA_HOURS_MAP['normal']['default'];

        return $start->modify("+{$hours} hours");
    }

    /**
     * Menilai status kepatuhan SLA tugas (ON_TIME, NEAR_BREACH, BREACHED).
     */
    public function evaluateSlaStatus(CoreTask $task, ?DateTimeInterface $currentTime = null): string
    {
        if ($task->due_at === null) {
            return 'NO_SLA';
        }

        $now = $currentTime ? $currentTime->getTimestamp() : time();
        $due = $task->due_at->getTimestamp();
        $diffSeconds = $due - $now;

        if ($diffSeconds < 0) {
            return 'BREACHED';
        }

        // Jika sisa waktu kurang dari 20% dari total alokasi atau < 4 jam
        if ($diffSeconds <= 14400) {
            return 'NEAR_BREACH';
        }

        return 'ON_TIME';
    }
}
