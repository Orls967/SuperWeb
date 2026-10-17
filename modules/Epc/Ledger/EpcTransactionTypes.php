<?php

declare(strict_types=1);

namespace Modules\Epc\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum EpcTransactionTypes: string implements TransactionTypeRegistry
{
    case EPC_PROGRESS_CERTIFICATE = 'EPC_PROGRESS_CERTIFICATE';
    case EPC_CIP_CAPITALIZATION = 'EPC_CIP_CAPITALIZATION';
}
