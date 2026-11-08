<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\ValueObjects;

use DateTimeImmutable;

final class ProposalQuotationTimelock
{
    public function __construct(
        public readonly DateTimeImmutable $issuedAt,
        public readonly int $validityHours = 72,
        public readonly bool $isAutoLockOnExpiry = true
    ) {}

    public function getExpiryTime(): DateTimeImmutable
    {
        return $this->issuedAt->modify("+{$this->validityHours} hours");
    }

    public function isExpired(DateTimeImmutable $checkTime): bool
    {
        return $checkTime > $this->getExpiryTime();
    }

    public function remainingSeconds(DateTimeImmutable $currentTime): int
    {
        $expiryTimestamp = $this->getExpiryTime()->getTimestamp();
        $currentTimestamp = $currentTime->getTimestamp();

        return max(0, $expiryTimestamp - $currentTimestamp);
    }
}
