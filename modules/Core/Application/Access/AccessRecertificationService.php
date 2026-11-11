<?php

declare(strict_types=1);

namespace Modules\Core\Application\Access;

use Illuminate\Support\Str;
use Modules\Core\Domain\Models\CoreAccessGrant;
use Modules\Core\Domain\Models\CoreAccessRecertificationCampaign;
use Modules\Core\Domain\Models\CoreAccessRecertificationItem;
use Modules\Core\Domain\StateMachines\AccessGrantStateMachine;

/**
 * Access Recertification Campaigns & Auto-Revocation Service (W8-D59).
 */
class AccessRecertificationService
{
    public function __construct(
        private readonly AccessGrantStateMachine $stateMachine
    ) {}

    /**
     * Create recertification campaign.
     *
     * @param array{
     *     campaign_code?: string,
     *     title: string,
     *     scope?: string,
     *     deadline: mixed
     * } $data
     */
    public function createCampaign(array $data): CoreAccessRecertificationCampaign
    {
        $code = $data['campaign_code'] ?? 'CAMP-'.strtoupper(Str::random(10));

        $campaign = CoreAccessRecertificationCampaign::create([
            'campaign_code' => $code,
            'title' => $data['title'],
            'scope' => $data['scope'] ?? 'all_active_grants',
            'deadline' => $data['deadline'],
            'status' => 'IN_PROGRESS',
        ]);

        // Enroll all active grants
        $activeGrants = CoreAccessGrant::where('status', AccessGrantStateMachine::STATUS_ACTIVE)->get();
        foreach ($activeGrants as $grant) {
            CoreAccessRecertificationItem::create([
                'campaign_id' => $campaign->id,
                'grant_id' => $grant->id,
                'response' => 'PENDING',
            ]);
        }

        return $campaign;
    }

    /**
     * Sweep overdue campaigns and auto-revoke pending items.
     */
    public function sweepOverdueCampaigns(): int
    {
        $overdueCampaigns = CoreAccessRecertificationCampaign::where('status', 'IN_PROGRESS')
            ->where('deadline', '<', now())
            ->get();

        $revokedCount = 0;

        foreach ($overdueCampaigns as $campaign) {
            $pendingItems = CoreAccessRecertificationItem::where('campaign_id', $campaign->id)
                ->where('response', 'PENDING')
                ->get();

            foreach ($pendingItems as $item) {
                $item->response = 'AUTO_REVOKED';
                $item->responded_at = now();
                $item->save();

                $grant = $item->grant;
                if ($grant !== null && $grant->status === AccessGrantStateMachine::STATUS_ACTIVE) {
                    $this->stateMachine->transition(
                        grant: $grant,
                        targetStatus: AccessGrantStateMachine::STATUS_REVOKED,
                        triggerAction: 'revoke',
                        reason: "RECERTIFICATION_DEADLINE_EXPIRED: Kampanye {$campaign->campaign_code} telah melewati tenggat waktu."
                    );
                    $revokedCount++;
                }
            }

            $campaign->status = 'COMPLETED';
            $campaign->save();
        }

        return $revokedCount;
    }
}
