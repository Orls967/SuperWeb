<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\DTOs;

final class AccountBalanceDTO
{
    public function __construct(
        public readonly string $canonicalAccountCode,
        public readonly string $accountName,
        public readonly int $debitMinor,
        public readonly int $creditMinor
    ) {}
}
