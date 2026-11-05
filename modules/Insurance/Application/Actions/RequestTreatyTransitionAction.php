<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\TreatyLifecycleService;
use Modules\Insurance\Domain\Enums\TreatyStatus;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;

final class RequestTreatyTransitionAction
{
    public function __construct(
        private readonly TreatyLifecycleService $lifecycle,
        private readonly ApprovalEngineInterface $approvalEngine,
        private readonly ClockInterface $clock,
    ) {}

    public function execute(ReinsuranceTreaty $treaty, TreatyStatus $targetStatus, User $user, ?string $reason = null): ReinsuranceTreaty
    {
        $treaty->status->assertCanTransitionTo($targetStatus);

        if (! $treaty->status->requiresApprovalTo($targetStatus)) {
            return DB::transaction(fn () => $this->lifecycle->apply($treaty, $targetStatus, $reason, $user->id));
        }

        return DB::transaction(function () use ($treaty, $targetStatus, $user, $reason) {
            $approval = $this->approvalEngine->submit(
                approvalType: 'REINSURANCE_TREATY_TRANSITION',
                title: "Persetujuan Transisi Treaty {$treaty->treaty_code} ke {$targetStatus->label()}",
                creator: $user,
                approvable: $treaty,
                amount: $treaty->limit_idr,
                steps: [
                    ['role' => 'reinsurance_mgr'],
                ],
                metadata: [
                    'treaty_id' => $treaty->id,
                    'target_status' => $targetStatus->value,
                    'reason' => $reason,
                ]
            );

            $approvalId = is_object($approval) && isset($approval->id) ? (int) $approval->id : 1;

            $treaty->forceFill([
                'pending_status' => $targetStatus,
                'approval_ref' => (string) $approvalId,
            ])->save();

            $treaty->history()->create([
                'from_status' => $treaty->status->value,
                'to_status' => $targetStatus->value,
                'reason' => "Menunggu persetujuan four-eyes untuk transisi ke {$targetStatus->label()}: {$reason}",
                'actor_user_id' => $user->id,
                'approval_ref' => $approvalId,
                'occurred_at' => $this->clock->now(),
            ]);

            return $treaty;
        });
    }
}
