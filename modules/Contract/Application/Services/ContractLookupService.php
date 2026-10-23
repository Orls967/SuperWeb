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
}
