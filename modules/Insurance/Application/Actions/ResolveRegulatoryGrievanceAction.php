<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\InsuranceGrievanceTicket;

final class ResolveRegulatoryGrievanceAction
{
    public function __construct(
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Menyelesaikan pengaduan nasabah atau mengeskalasi ke regulator OJK (Fase 160.5).
     */
    public function execute(
        string $ticketNumber,
        string $resolutionStatus, // resolved, escalated_ojk
        string $resolutionSummary,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): InsuranceGrievanceTicket {
        $ticket = InsuranceGrievanceTicket::where('ticket_number', $ticketNumber)->firstOrFail();
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('resolve:'.$ticketNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:compliance:resolve:'.$ticketNumber,
            payload: [
                'ticket_number' => $ticketNumber,
                'status' => $resolutionStatus,
                'summary' => $resolutionSummary,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($ticket, $resolutionStatus, $resolutionSummary) {
                $now = CarbonImmutable::now();

                $ticket->update([
                    'status' => $resolutionStatus,
                    'resolution_summary' => $resolutionSummary,
                    'resolved_at' => $resolutionStatus === 'resolved' ? $now : null,
                ]);

                return $ticket;
            }
        );
    }
}
