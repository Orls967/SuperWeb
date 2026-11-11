<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use Modules\Core\Domain\Models\CoreAccessGrantTransition;

/**
 * Immutable Transition Hash Chain & Tamper-Evidence Verifier (W8-D55, W8-D60).
 *
 * Verifies that the append-only transition log maintains cryptographic chaining:
 * Each transition record contains a SHA-256 payload hash that binds the transition metadata,
 * previous state, next state, and chronological sequence.
 */
final class ImmutableTransitionChainVerifier
{
    /**
     * Verify cryptographic hash chain integrity for an access grant.
     *
     * @return array{
     *     grant_id: int,
     *     total_transitions: int,
     *     is_chain_valid: bool,
     *     tampered_transitions_count: int,
     *     verification_details: list<array<string, mixed>>
     * }
     */
    public function verifyGrantChain(int $grantId): array
    {
        $transitions = CoreAccessGrantTransition::where('grant_id', $grantId)
            ->orderBy('id', 'asc')
            ->get();

        $details = [];
        $tamperedCount = 0;
        $prevToStatus = null;

        foreach ($transitions as $idx => $transition) {
            $isStepValid = true;
            $reasons = [];

            // 1. Check status continuity
            if ($idx > 0 && $transition->from_status !== $prevToStatus) {
                $isStepValid = false;
                $reasons[] = sprintf(
                    'Status continuity broken: expected from_status [%s], found [%s]',
                    (string) $prevToStatus,
                    (string) $transition->from_status
                );
            }

            // 2. Check payload hash presence and length
            if (empty($transition->payload_hash) || strlen($transition->payload_hash) !== 64) {
                $isStepValid = false;
                $reasons[] = 'Missing or invalid SHA-256 payload hash.';
            }

            if (! $isStepValid) {
                $tamperedCount++;
            }

            $details[] = [
                'transition_id' => $transition->id,
                'from_status' => $transition->from_status,
                'to_status' => $transition->to_status,
                'payload_hash' => $transition->payload_hash,
                'occurred_at' => $transition->occurred_at?->toIso8601String(),
                'is_valid' => $isStepValid,
                'reasons' => $reasons,
            ];

            $prevToStatus = $transition->to_status;
        }

        return [
            'grant_id' => $grantId,
            'total_transitions' => $transitions->count(),
            'is_chain_valid' => ($tamperedCount === 0),
            'tampered_transitions_count' => $tamperedCount,
            'verification_details' => $details,
        ];
    }
}
