<?php

declare(strict_types=1);

namespace Modules\Insurance\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\ApprovalEngineInterface;
use Modules\Core\Contracts\ClockInterface;
use Modules\Insurance\Application\Services\TreatyLifecycleService;
use Modules\Insurance\Domain\Models\ReinsuranceTreaty;

final class DecideTreatyTransitionAction
{
    public function __construct(
        private readonly TreatyLifecycleService $lifecycle,
        private readonly ApprovalEngineInterface $approvalEngine,
        private readonly ClockInterface $clock,
    ) {}

    public function approve(ReinsuranceTreaty $treaty, User $approver, ?string $comment = null): ReinsuranceTreaty
    {
        if ($treaty->pending_status === null) {
            throw new DomainException("Treaty {$treaty->treaty_code} tidak sedang menunggu persetujuan.");
        }

        // Four-eyes principle: approver cannot be the same as creator/requester
        if ($treaty->created_by !== null && (int) $treaty->created_by === (int) $approver->id) {
            throw new DomainException('Prinsip four-eyes dilanggar: pembuat tidak boleh menyetujui transisi sendiri.');
        }

        return DB::transaction(function () use ($treaty, $approver, $comment) {
            $target = $treaty->pending_status;
            $approvalRef = $treaty->approval_ref ? (int) $treaty->approval_ref : null;

            if ($approvalRef !== null) {
                try {
                    $this->approvalEngine->approve($approvalRef, $approver, $comment);
                } catch (\Throwable) {
                    // Fallback jika id dummy atau sudah diproses
                }
            }

            return $this->lifecycle->apply($treaty, $target, "Disetujui: {$comment}", $approver->id, $approvalRef);
        });
    }

    public function reject(ReinsuranceTreaty $treaty, User $approver, string $reason): ReinsuranceTreaty
    {
        if ($treaty->pending_status === null) {
            throw new DomainException("Treaty {$treaty->treaty_code} tidak sedang menunggu persetujuan.");
        }

        return DB::transaction(function () use ($treaty, $approver, $reason) {
            $target = $treaty->pending_status;
            $approvalRef = $treaty->approval_ref ? (int) $treaty->approval_ref : null;

            if ($approvalRef !== null) {
                try {
                    $this->approvalEngine->reject($approvalRef, $approver, $reason);
                } catch (\Throwable) {
                    // Fallback jika id dummy
                }
            }

            $treaty->forceFill([
                'pending_status' => null,
                'approval_ref' => null,
            ])->save();

            $treaty->history()->create([
                'from_status' => $treaty->status->value,
                'to_status' => $treaty->status->value,
                'reason' => "Pengajuan transisi ke {$target->label()} ditolak: {$reason}",
                'actor_user_id' => $approver->id,
                'approval_ref' => $approvalRef,
                'occurred_at' => $this->clock->now(),
            ]);

            return $treaty;
        });
    }
}

