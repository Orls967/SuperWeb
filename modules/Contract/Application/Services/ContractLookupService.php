<?php

declare(strict_types=1);

namespace Modules\Contract\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Contract\Contracts\ContractLookupInterface;

class ContractLookupService implements ContractLookupInterface
{
    public function getContractNumber(int $contractId): ?string
    {
        $val = DB::table('ctr_contracts')->where('id', $contractId)->value('contract_number');

        return $val !== null ? (string) $val : null;
    }

    public function getContract(string|int $contractIdOrNumber): ?array
    {
        $query = DB::table('ctr_contracts');
        if (is_numeric($contractIdOrNumber)) {
            $query->where('id', (int) $contractIdOrNumber)
                ->orWhere('contract_number', (string) $contractIdOrNumber);
        } else {
            $query->where('contract_number', (string) $contractIdOrNumber);
        }

        $row = $query->first();

        return $row !== null ? (array) $row : null;
    }
}
