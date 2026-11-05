<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Insurance\Application\Services\IdempotentRequestGuard;
use Modules\Insurance\Domain\Models\InsuranceGrievanceTicket;

final class SubmitRegulatoryGrievanceAction
{
    public function __construct(
        private readonly IdempotentRequestGuard $guard,
    ) {}

    /**
     * Mencatat tiket pengaduan nasabah dan monitoring kepatuhan regulator SLA (Fase 160.5).
     */
    public function execute(
        string $ticketNumber,
        string $policyRef,
        string $complainantPartyId,
        string $category,
        string $grievanceText,
        int $slaDays = 14,
        string $actorId = '1',
        string $idempotencyKey = '',
    ): InsuranceGrievanceTicket {
        $key = $idempotencyKey !== '' ? $idempotencyKey : ('grievance:'.$ticketNumber);

        return $this->guard->run(
            key: $key,
            scope: 'ins:compliance:grievance:'.$ticketNumber,
            payload: [
                'ticket_number' => $ticketNumber,
                'policy_ref' => $policyRef,
                'category' => $category,
            ],
            actorId: is_numeric($actorId) ? (int) $actorId : null,
            operation: function () use ($ticketNumber, $policyRef, $complainantPartyId, $category, $grievanceText, $slaDays) {
                $now = CarbonImmutable::now();

                return InsuranceGrievanceTicket::create([
                    'ticket_number' => $ticketNumber,
                    'policy_ref' => $policyRef,
                    'complainant_party_id' => $complainantPartyId,
                    'category' => $category,
                    'grievance_text' => $grievanceText,
                    'sla_deadline' => $now->addDays($slaDays)->toDateString(),
                    'status' => 'open',
                ]);
            }
        );
    }
}
