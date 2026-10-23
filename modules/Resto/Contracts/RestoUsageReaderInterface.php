<?php

declare(strict_types=1);

namespace Modules\Resto\Contracts;

use Illuminate\Support\Collection;

interface RestoUsageReaderInterface
{
    /** @return array<int, array{id: int, royalty_amount: int, date: string}> */
    public function getRoyaltyPostingsForContract(string $contractId): array;

    /**
     * @param  array<int, string>  $partyIds
     * @return array<int, array{id: int, total_amount: int, po_number: string}>
     */
    public function getPurchaseOrdersForSupplierParties(array $partyIds): array;

    public function getPurchaseOrdersForSupplierParty(string $partyId, int $limit = 10): Collection;

    public function getOutletIdByCode(string $code): ?int;
}
