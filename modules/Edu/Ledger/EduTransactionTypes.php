<?php

declare(strict_types=1);

namespace Modules\Edu\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum EduTransactionTypes: string implements TransactionTypeRegistry
{
    case EDU_HEADHUNTER_FEE_RELEASE = 'EDU_HEADHUNTER_FEE_RELEASE';
    case EDU_TUITION_PAYMENT = 'EDU_TUITION_PAYMENT';
    case EDU_TUITION_PRO_RATA_REFUND = 'EDU_TUITION_PRO_RATA_REFUND';
}
