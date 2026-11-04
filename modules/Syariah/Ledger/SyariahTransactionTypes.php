<?php

declare(strict_types=1);

namespace Modules\Syariah\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum SyariahTransactionTypes: string implements TransactionTypeRegistry
{
    case SYB_MURABAHAH_PURCHASE = 'SYB_MURABAHAH_PURCHASE';
    case SYB_MURABAHAH_SIGN = 'SYB_MURABAHAH_SIGN';
    case SYB_MURABAHAH_INSTALLMENT = 'SYB_MURABAHAH_INSTALLMENT';
    case SYB_MURABAHAH_MARGIN_REC = 'SYB_MURABAHAH_MARGIN_REC';
    case SYB_MURABAHAH_TAZIR = 'SYB_MURABAHAH_TAZIR';
    case SYB_WADIAH_FUNDING = 'SYB_WADIAH_FUNDING';
}
