<?php

declare(strict_types=1);

namespace Modules\Insurance\Contracts;

use Carbon\CarbonImmutable;

interface EventCoverBinder
{
    /**
     * @return array{policy_id: int, premium_minor: int}
     */
    public function bind(
        int $eventId,
        int $sumInsuredMinor,
        int $attendanceCap,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $idempotencyKey
    ): array;

    /**
     * @return int id klaim
     */
    public function claimCancellation(
        int $policyId,
        string $reasonCode,
        int $lossMinor,
        string $idempotencyKey
    ): int;
}
