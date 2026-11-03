<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum ManufacturingTransactionTypes: string implements TransactionTypeRegistry
{
    case VMI_PURCHASE_COMMITMENT = 'VMI_PURCHASE_COMMITMENT';
    case RECYCLED_MATERIAL_INTAKE = 'RECYCLED_MATERIAL_INTAKE';
    case RECYCLED_SUPPLIER_PAYABLE = 'RECYCLED_SUPPLIER_PAYABLE';
}
