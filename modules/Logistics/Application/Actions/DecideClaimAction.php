<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\ClaimException;
use Modules\Logistics\Domain\Models\Claim;

class DecideClaimAction
{
    /**
     * Alur 11 (langkah 3): penyetuju menyetujui (dengan nilai) atau menolak. Penyetuju harus admin/admin logistik
     * dan berbeda dari pembuat maupun pengaju. Penolakan membebaskan kunci aktif resi.
     */
    public function execute(User $approver, Claim $claim, bool $approve, ?int $approvedAmountIdr, string $notes): Claim
    {
        if (! ($approver->isAdmin() || $approver->isLogisticsAdmin())) {
            throw ClaimException::forbidden('memutuskan klaim');
        }

        return DB::transaction(function () use ($approver, $claim, $approve, $approvedAmountIdr, $notes) {
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->status !== Claim::STATUS_SUBMITTED) {
                throw ClaimException::invalidState($claim->status, Claim::STATUS_SUBMITTED);
            }

            if (in_array($approver->id, [$claim->created_by, $claim->submitted_by], true)) {
                throw ClaimException::fourEyes('penyetuju tidak boleh sama dengan pembuat atau pengaju klaim.');
            }

            if ($approve) {
                $amount = $approvedAmountIdr ?? $claim->claimed_amount_idr;
                $limit = min($claim->claimed_amount_idr, $claim->cap_amount_idr);
                if ($amount <= 0 || $amount > $limit) {
                    throw ClaimException::exceedsCap($amount, $limit);
                }

                $claim->update([
                    'status' => Claim::STATUS_APPROVED,
                    'approved_amount_idr' => $amount,
                    'decided_by' => $approver->id,
                    'decided_at' => now(),
                    'decision_notes' => mb_substr($notes, 0, 500),
                ]);
            } else {
                $claim->update([
                    'status' => Claim::STATUS_REJECTED,
                    'active_key' => null,
                    'decided_by' => $approver->id,
                    'decided_at' => now(),
                    'decision_notes' => mb_substr($notes, 0, 500),
                ]);
            }

            return $claim;
        });
    }
}
