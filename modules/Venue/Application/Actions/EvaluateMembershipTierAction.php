<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Algorithms\MembershipTierEngine;
use Modules\Venue\Domain\Algorithms\MembershipTierInput;
use Modules\Venue\Domain\Models\VenueMembership;
use Modules\Venue\Domain\StateMachines\MembershipStateMachine;

final class EvaluateMembershipTierAction extends BaseAction
{
    public function __construct(
        private readonly MembershipTierEngine $engine
    ) {}

    public function execute(
        int $userId,
        MembershipTierInput $input,
        ?string $assignedLockerNumber = null
    ): VenueMembership {
        return DB::transaction(function () use ($userId, $input, $assignedLockerNumber) {
            // 1. Evaluasi Tingkat Keanggotaan via Algoritma V7
            $result = $this->engine->evaluate($input);

            // 2. Validasi Sertifikat Komputasi
            $result->certificate->isValid();

            // 3. Generate Nomor Anggota Unik
            $hashEntropy = hash('sha256', "MEMB:{$userId}");
            $memberNumber = 'MBR-'.strtoupper(substr($hashEntropy, 0, 10));

            $lockerToAssign = $result->eligibleForBottleLocker ? ($assignedLockerNumber ?? 'LCK-'.substr($hashEntropy, 0, 6)) : null;

            $status = $result->inGracePeriod ? MembershipStateMachine::STATUS_GRACE : MembershipStateMachine::STATUS_ACTIVE;

            // 4. Perbarui atau Buat Record Keanggotaan
            /** @var VenueMembership $membership */
            $membership = VenueMembership::query()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'id' => $memberNumber,
                    'membership_number' => $memberNumber,
                    'tier' => $result->resolvedTier,
                    'loyalty_points' => $this->engine->calculatePoints($input->spendCurrentYearIdr, $result->resolvedTier),
                    'spend_365_days_idr' => $input->spend365DaysIdr,
                    'visits_365_days' => $input->visits365Days,
                    'bottle_locker_number' => $lockerToAssign,
                    'grace_period_expires_at' => $result->inGracePeriod ? Carbon::now()->addDays(30) : null,
                    'discount_rate' => match ($result->resolvedTier) {
                        'infinity' => 0.20,
                        'moon' => 0.15,
                        default => 0.10,
                    },
                    'status' => $status,
                    'expires_at' => Carbon::now()->addYear(),
                ]
            );

            return $membership;
        });
    }
}
