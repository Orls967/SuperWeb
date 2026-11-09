<?php

declare(strict_types=1);

namespace Modules\Platform\Domain\Change;

use Modules\Platform\Domain\Models\ChangeRequest;

final class CabMeetingAgendaBuilder
{
    /**
     * Builds weekly CAB review meeting agenda item list ordered by risk severity.
     *
     * @param  list<ChangeRequest>  $pendingRequests
     * @return array{meeting_title: string, agenda_items: list<array{order: int, reference: string, title: string, module: string, score: float, tier: string, estimated_minutes: int}>, total_estimated_minutes: int}
     */
    public function buildAgenda(array $pendingRequests): array
    {
        // Sort descending by risk score
        usort($pendingRequests, fn (ChangeRequest $a, ChangeRequest $b): int => $b->risk_score <=> $a->risk_score);

        $agendaItems = [];
        $totalMinutes = 0;
        $order = 1;

        foreach ($pendingRequests as $cr) {
            $duration = match ($cr->risk_tier) {
                'critical' => 20,
                'high' => 15,
                'medium' => 10,
                default => 5,
            };

            $agendaItems[] = [
                'order' => $order++,
                'reference' => $cr->reference_number,
                'title' => $cr->title,
                'module' => $cr->target_module,
                'score' => round($cr->risk_score / 100.0, 2),
                'tier' => $cr->risk_tier,
                'estimated_minutes' => $duration,
            ];

            $totalMinutes += $duration;
        }

        return [
            'meeting_title' => 'Weekly Change Advisory Board (CAB) Quorum Review',
            'agenda_items' => $agendaItems,
            'total_estimated_minutes' => $totalMinutes,
        ];
    }
}
