<?php

declare(strict_types=1);

namespace Modules\Venue\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Shared\Application\BaseAction;
use Modules\Venue\Domain\Models\VenueMembership;

final class AssignBottleLockerAction extends BaseAction
{
    public function execute(
        string $membershipNumber,
        string $lockerNumber
    ): VenueMembership {
        return DB::transaction(function () use ($membershipNumber, $lockerNumber) {
            /** @var VenueMembership|null $membership */
            $membership = VenueMembership::query()->where('membership_number', $membershipNumber)->first();
            if (! $membership) {
                throw new InvalidArgumentException("Membership number {$membershipNumber} tidak ditemukan.");
            }

            if (strtolower($membership->tier) !== 'infinity') {
                throw new DomainException('Fasilitas loker botol penyimpanan VIP hanya berhak untuk anggota tier Infinity.');
            }

            $membership->update([
                'bottle_locker_number' => $lockerNumber,
            ]);

            return $membership;
        });
    }
}
