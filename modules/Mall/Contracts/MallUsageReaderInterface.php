<?php

declare(strict_types=1);

namespace Modules\Mall\Contracts;

interface MallUsageReaderInterface
{
    public function getTenantOutstandingExposure(string $partyId): int;

    /**
     * @return array<int, array{id: int, total_amount: int, invoice_number: string}>
     */
    public function getInvoicesForLease(int $leaseId): array;
}
