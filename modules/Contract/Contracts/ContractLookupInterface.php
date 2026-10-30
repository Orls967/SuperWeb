<?php

declare(strict_types=1);

namespace Modules\Contract\Contracts;

interface ContractLookupInterface
{
    public function getContractNumber(int $contractId): ?string;

    /**
     * @return array<string, mixed>|null
     */
    public function getContract(string|int $contractIdOrNumber): ?array;
}
