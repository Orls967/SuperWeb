<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;
use Modules\Core\Domain\Models\CoreIdentityLink;

/**
 * Two-Source Identity & Access Governance Auditor (W8-D60).
 *
 * Source A: Current relational state in core_access_grants, core_identity_links.
 * Source B: Append-only immutable transitions in core_access_grant_transitions.
 */
class CoreAccessAuditor
{
    /**
     * Audit access governance integrity against immutable transition logs.
     *
     * @return array{status: int, discrepancies: list<string>, audited_grants: int, audited_links: int}
     */
    public function audit(): array
    {
        $discrepancies = [];

        $grants = CoreAccessGrant::all();
        $links = CoreIdentityLink::all();

        foreach ($grants as $grant) {
            // Check 1: Active grant must not be past valid_to
            if ($grant->status === 'active' && $grant->valid_to !== null && $grant->valid_to->isPast()) {
                $discrepancies[] = "Grant #{$grant->grant_code} berstatus active padahal telah melewati masa berlaku valid_to ({$grant->valid_to->toIso8601String()}).";
            }

            // Check 2: Active or provisioned grant must have an 'approved' transition in history
            if (in_array($grant->status, ['active', 'provisioned'], true)) {
                $hasApproval = CoreAccessGrantTransition::where('grant_id', $grant->id)
                    ->where('to_status', 'approved')
                    ->exists();

                if (! $hasApproval) {
                    $discrepancies[] = "Grant #{$grant->grant_code} berstatus {$grant->status} tanpa riwayat transisi approved di core_access_grant_transitions.";
                }
            }

            // Check 3: Revoked grant must have revoked_at populated
            if ($grant->status === 'revoked' && $grant->revoked_at === null) {
                $discrepancies[] = "Grant #{$grant->grant_code} berstatus revoked namun revoked_at bernilai null.";
            }
        }

        foreach ($links as $link) {
            // Check 4: Double consent invariant on active links
            if ($link->is_active && (! $link->source_consent || ! $link->target_consent)) {
                $discrepancies[] = "Tautan identitas #{$link->link_code} aktif tanpa persetujuan ganda (source: ".($link->source_consent ? '1' : '0').', target: '.($link->target_consent ? '1' : '0').').';
            }
        }

        $status = count($discrepancies) === 0 ? 0 : 1;

        return [
            'status' => $status,
            'discrepancies' => $discrepancies,
            'audited_grants' => $grants->count(),
            'audited_links' => $links->count(),
        ];
    }
}
