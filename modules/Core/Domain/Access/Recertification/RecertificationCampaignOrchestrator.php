<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Access\Recertification;

use DomainException;
use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessGrantTransition;
use Modules\Core\Domain\Models\CoreAccessRecertificationCampaign;
use Modules\Core\Domain\Models\CoreAccessRecertificationItem;

/**
 * Access Recertification Campaign Lifecycle Orchestrator (W8-D59).
 *
 * Manages periodic access recertification campaigns (Quarterly / Semi-Annual).
 * Generates audit items for each active grant, tracks supervisor SLAs,
 * and executes automated revocation on delinquent reviews.
 */
final class RecertificationCampaignOrchestrator
{
    /**
     * Launch a new recertification campaign.
     *
     * @param  int  $durationDays  SLA duration before auto-revocation triggers
     * @param  int|null  $managerId  Optional specific manager scope
     */
    public function launchCampaign(
        string $tenantId,
        string $campaignName,
        int $durationDays = 14,
        ?int $managerId = null
    ): CoreAccessRecertificationCampaign {
        $code = 'REC-'.strtoupper(Str::random(8));
        $now = now();
        $dueAt = $now->copy()->addDays($durationDays);

        $campaign = CoreAccessRecertificationCampaign::create([
            'campaign_code' => $code,
            'tenant_id' => $tenantId,
            'name' => $campaignName,
            'frequency' => 'QUARTERLY',
            'status' => 'ACTIVE',
            'started_at' => $now,
            'due_at' => $dueAt,
            'total_items' => 0,
            'completed_items' => 0,
        ]);

        // Find all active grants in tenant
        $activeGrants = CoreAccessGrant::where('tenant_id', $tenantId)
            ->where('status', 'ACTIVE')
            ->get();

        $itemsCreated = 0;
        foreach ($activeGrants as $grant) {
            $reviewer = $managerId ?? ($grant->authorized_by ?? $grant->requested_by ?? 1);

            CoreAccessRecertificationItem::create([
                'campaign_id' => $campaign->id,
                'grant_id' => $grant->id,
                'reviewer_id' => $reviewer,
                'decision' => 'PENDING',
                'decision_notes' => null,
                'decided_at' => null,
            ]);
            $itemsCreated++;
        }

        $campaign->total_items = $itemsCreated;
        $campaign->save();

        return $campaign;
    }

    /**
     * Process reviewer decision on an item.
     *
     * @param  string  $decision  CERTIFIED or REVOKED
     */
    public function recordItemDecision(
        int $itemId,
        int $reviewerId,
        string $decision,
        string $notes
    ): CoreAccessRecertificationItem {
        $item = CoreAccessRecertificationItem::with(['campaign', 'grant'])->findOrFail($itemId);

        if ($item->decision !== 'PENDING') {
            throw new DomainException("Recertification item #{$itemId} has already been decided: {$item->decision}");
        }

        if (! in_array($decision, ['CERTIFIED', 'REVOKED'], true)) {
            throw new DomainException("Invalid recertification decision: {$decision}. Must be CERTIFIED or REVOKED.");
        }

        $now = now();
        $item->decision = $decision;
        $item->reviewer_id = $reviewerId;
        $item->decision_notes = $notes;
        $item->decided_at = $now;
        $item->save();

        // If decision is REVOKED, immediately revoke the underlying grant
        if ($decision === 'REVOKED' && $item->grant !== null) {
            $grant = $item->grant;
            $oldStatus = $grant->status;
            $grant->status = 'REVOKED';
            $grant->save();

            CoreAccessGrantTransition::create([
                'grant_id' => $grant->id,
                'from_status' => $oldStatus,
                'to_status' => 'REVOKED',
                'transition_code' => 'RECERTIFICATION_REJECTED',
                'reason' => "Revoked during campaign [{$item->campaign?->campaign_code}]: {$notes}",
                'performed_by' => $reviewerId,
                'payload_hash' => hash('sha256', "RECERT_REVOKE:{$item->id}:{$grant->id}"),
                'occurred_at' => $now,
            ]);
        }

        // Update campaign progress
        if ($item->campaign !== null) {
            $campaign = $item->campaign;
            $campaign->completed_items = CoreAccessRecertificationItem::where('campaign_id', $campaign->id)
                ->where('decision', '!=', 'PENDING')
                ->count();

            if ($campaign->completed_items >= $campaign->total_items && $campaign->total_items > 0) {
                $campaign->status = 'COMPLETED';
            }
            $campaign->save();
        }

        return $item;
    }

    /**
     * Auto-revoke all pending items in overdue campaigns.
     *
     * @return array{overdue_campaigns: int, auto_revoked_grants: int}
     */
    public function sweepOverdueCampaigns(): array
    {
        $now = now();
        $overdueCampaigns = CoreAccessRecertificationCampaign::where('status', 'ACTIVE')
            ->where('due_at', '<', $now)
            ->get();

        $revokedCount = 0;

        foreach ($overdueCampaigns as $campaign) {
            $pendingItems = CoreAccessRecertificationItem::where('campaign_id', $campaign->id)
                ->where('decision', 'PENDING')
                ->with('grant')
                ->get();

            foreach ($pendingItems as $item) {
                $item->decision = 'AUTO_REVOKED';
                $item->decision_notes = 'Automated revocation due to reviewer SLA breach.';
                $item->decided_at = $now;
                $item->save();

                if ($item->grant !== null && $item->grant->status === 'ACTIVE') {
                    $grant = $item->grant;
                    $grant->status = 'REVOKED';
                    $grant->save();

                    CoreAccessGrantTransition::create([
                        'grant_id' => $grant->id,
                        'from_status' => 'ACTIVE',
                        'to_status' => 'REVOKED',
                        'transition_code' => 'SLA_AUTO_REVOKE',
                        'reason' => "Reviewer SLA expired in campaign [{$campaign->campaign_code}]",
                        'performed_by' => null,
                        'payload_hash' => hash('sha256', "AUTO_RECERT:{$item->id}:{$grant->id}"),
                        'occurred_at' => $now,
                    ]);

                    $revokedCount++;
                }
            }

            $campaign->status = 'OVERDUE';
            $campaign->completed_items = $campaign->total_items;
            $campaign->save();
        }

        return [
            'overdue_campaigns' => $overdueCampaigns->count(),
            'auto_revoked_grants' => $revokedCount,
        ];
    }
}
