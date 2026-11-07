<?php

declare(strict_types=1);

namespace App\Quality\Audit\Fixtures;

use App\Quality\Audit\CorruptionFixture;
use Modules\Venue\Domain\Models\VenueMembership;

final class VenEconomyAuditCorruptionFixture implements CorruptionFixture
{
    public function __construct(
        private readonly string $commandName = 'venue:audit-economy'
    ) {}

    public function command(): string
    {
        return $this->commandName;
    }

    public function corrupt(): string
    {
        VenueMembership::query()->create([
            'id' => '99999999-9999-9999-9999-999999999999',
            'user_id' => '88888888-8888-8888-8888-888888888888',
            'membership_number' => 'MEM-CORRUPT-99',
            'tier' => 'Sun',
            'loyalty_points' => 100,
            'spend_365_days_idr' => 1000000,
            'bottle_locker_number' => 'LOCKER-ILLEGAL-99',
            'status' => 'ACTIVE',
        ]);

        return 'AUDIT EKONOMI VENUE GAGAL';
    }
}
