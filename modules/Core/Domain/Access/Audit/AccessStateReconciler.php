<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Audit;

use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;

/**
 * Two-Source Access Ledger Reconciler (W8-D60).
 *
 * Implements mathematical reconciliation between:
 * - Source 1: Mutable State Table (`core_access_grants`)
 * - Source 2: Immutable Append-Only Ledger (`core_access_grant_transitions`)
 *
 * Invariant: For every grant in status S, the latest transition in the ledger
 * MUST point to status S, and the transition hash chain must be contiguous.
 */
final class AccessStateReconciler
{
    /**
     * Run full reconciliation across all grants in a tenant or entire system.
     *
     * @param  string|null  $tenantId  Optional tenant scope
     * @return array{
     *     total_grants: int,
     *     total_transitions: int,
     *     reconciled_grants: int,
     *     divergent_grants: int,
     *     ghost_grants: int,
     *     orphaned_transitions: int,
     *     discrepancies: list<array<string, mixed>>
     * }
     */
    public function reconcile(?string $tenantId = null): array
    {
        $grantQuery = CoreAccessGrant::query();
        if ($tenantId !== null) {
            $grantQuery->where('tenant_id', $tenantId);
        }
        $grants = $grantQuery->get();

        $transitionQuery = CoreAccessGrantTransition::query();
        if ($tenantId !== null) {
            $grantIds = $grants->pluck('id')->all();
            $transitionQuery->whereIn('grant_id', $grantIds);
        }
        $transitions = $transitionQuery->orderBy('id', 'asc')->get();

        // Group transitions by grant_id
        $transitionsByGrant = [];
        foreach ($transitions as $t) {
            $transitionsByGrant[$t->grant_id][] = $t;
        }

        $reconciled = 0;
        $divergent = 0;
        $ghost = 0;
        $discrepancies = [];

        foreach ($grants as $grant) {
            $grantTransitions = $transitionsByGrant[$grant->id] ?? [];

            if (empty($grantTransitions)) {
                // Ghost grant: exists in state table but has zero ledger history!
                $ghost++;
                $discrepancies[] = [
                    'grant_id' => $grant->id,
                    'grant_code' => $grant->grant_code,
                    'type' => 'GHOST_GRANT',
                    'detail' => "Grant #{$grant->id} ({$grant->grant_code}) exists in state table but has NO transitions in the append-only ledger.",
                ];

                continue;
            }

            // Verify latest transition matches current state
            /** @var CoreAccessGrantTransition $latestTransition */
            $latestTransition = end($grantTransitions);

            if ($latestTransition->to_status !== $grant->status) {
                // Status divergence!
                $divergent++;
                $discrepancies[] = [
                    'grant_id' => $grant->id,
                    'grant_code' => $grant->grant_code,
                    'type' => 'STATUS_DIVERGENCE',
                    'state_status' => $grant->status,
                    'ledger_status' => $latestTransition->to_status,
                    'detail' => "State status [{$grant->status}] differs from latest ledger transition [{$latestTransition->to_status}].",
                ];

                continue;
            }

            // Verify chronological integrity of transition chain
            $chainIntact = true;
            $prevToStatus = null;
            foreach ($grantTransitions as $idx => $tr) {
                if ($idx > 0 && $tr->from_status !== $prevToStatus) {
                    $chainIntact = false;
                    $discrepancies[] = [
                        'grant_id' => $grant->id,
                        'grant_code' => $grant->grant_code,
                        'type' => 'CHAIN_CONTINUITY_BROKEN',
                        'detail' => "Transition #{$tr->id} from_status [{$tr->from_status}] does not match previous to_status [{$prevToStatus}].",
                    ];
                    break;
                }
                $prevToStatus = $tr->to_status;
            }

            if ($chainIntact) {
                $reconciled++;
            } else {
                $divergent++;
            }
        }

        // Check for orphaned transitions (pointing to non-existent grant)
        $existingGrantIds = $grants->pluck('id')->all();
        $orphanedTransitions = 0;
        foreach ($transitions as $tr) {
            if (! in_array($tr->grant_id, $existingGrantIds, true)) {
                $orphanedTransitions++;
                $discrepancies[] = [
                    'transition_id' => $tr->id,
                    'grant_id' => $tr->grant_id,
                    'type' => 'ORPHANED_TRANSITION',
                    'detail' => "Transition #{$tr->id} references non-existent grant ID {$tr->grant_id}.",
                ];
            }
        }

        return [
            'total_grants' => $grants->count(),
            'total_transitions' => $transitions->count(),
            'reconciled_grants' => $reconciled,
            'divergent_grants' => $divergent,
            'ghost_grants' => $ghost,
            'orphaned_transitions' => $orphanedTransitions,
            'discrepancies' => $discrepancies,
        ];
    }
}
