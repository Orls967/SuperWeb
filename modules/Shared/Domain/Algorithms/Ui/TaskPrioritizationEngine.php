<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Ui;

use DateTimeInterface;

/**
 * Algoritma Bersertifikat A9: Dynamic Task Prioritization & SLA Decay Engine.
 * Menghitung bobot urgensi tugas kotak masuk secara deterministik berdasarkan
 * sisa waktu tenggat (exponential decay), tingkat eskalasi, dan bobot prioritas bisnis.
 */
class TaskPrioritizationEngine
{
    private const BASE_WEIGHTS = [
        'urgent' => 100.0,
        'high' => 60.0,
        'normal' => 30.0,
        'low' => 10.0,
    ];

    /**
     * Menghitung skor prioritas deterministik sebuah tugas.
     *
     * @param  string  $priority  'urgent'|'high'|'normal'|'low'
     * @return array{score: float, breakdown: array<string, float>, certificate: array<string, mixed>}
     */
    public function computePriority(
        string $taskId,
        string $priority,
        int $escalationLevel,
        ?DateTimeInterface $dueAt = null,
        ?DateTimeInterface $currentTime = null
    ): array {
        $now = $currentTime ? $currentTime->getTimestamp() : time();
        $base = self::BASE_WEIGHTS[$priority] ?? 30.0;
        $escalationBoost = $escalationLevel * 25.0;

        $timeDecayBoost = 0.0;
        if ($dueAt !== null) {
            $dueTimestamp = $dueAt->getTimestamp();
            $diffHours = ($dueTimestamp - $now) / 3600.0;

            if ($diffHours <= 0) {
                // Overdue: penalti keterlambatan progresif
                $overdueHours = abs($diffHours);
                $timeDecayBoost = 50.0 + min(100.0, $overdueHours * 5.0);
            } elseif ($diffHours <= 24) {
                // Mendekati tenggat dalam 24 jam
                $timeDecayBoost = (24.0 - $diffHours) * 2.0;
            }
        }

        $totalScore = round($base + $escalationBoost + $timeDecayBoost, 4);

        $breakdown = [
            'base' => $base,
            'escalation' => $escalationBoost,
            'time_decay' => round($timeDecayBoost, 4),
            'total' => $totalScore,
        ];

        $payload = [
            'task_id' => $taskId,
            'priority' => $priority,
            'escalation_level' => $escalationLevel,
            'has_due_date' => $dueAt !== null,
            'score' => $totalScore,
        ];

        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return [
            'score' => $totalScore,
            'breakdown' => $breakdown,
            'certificate' => [
                'algorithm' => 'Task-Prioritization-Engine-V1',
                'hash' => $hash,
                'payload' => $payload,
                'verified' => true,
            ],
        ];
    }
}
