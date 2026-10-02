<?php

declare(strict_types=1);

namespace Modules\Logistics\Application\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Logistics\Domain\Exceptions\ClaimException;
use Modules\Logistics\Domain\Models\Claim;

class SubmitClaimAction
{
    /**
     * Alur 11 (langkah 2): pengaju mengajukan klaim untuk ditinjau. Pengaju harus staf dan berbeda dari pembuat.
     */
    public function execute(User $submitter, Claim $claim): Claim
    {
        if (! ($submitter->isAdmin() || $submitter->isLogisticsAdmin() || $submitter->isDispatcher())) {
            throw ClaimException::forbidden('mengajukan klaim');
        }

        return DB::transaction(function () use ($submitter, $claim) {
            $claim = Claim::whereKey($claim->id)->lockForUpdate()->firstOrFail();

            if ($claim->status !== Claim::STATUS_DRAFT) {
                throw ClaimException::invalidState($claim->status, Claim::STATUS_DRAFT);
            }

            if ($claim->created_by === $submitter->id) {
                throw ClaimException::fourEyes('pengaju klaim tidak boleh sama dengan pembuat klaim.');
            }

            $claim->update([
                'status' => Claim::STATUS_SUBMITTED,
                'submitted_by' => $submitter->id,
                'submitted_at' => now(),
            ]);

            return $claim;
        });
    }
}
