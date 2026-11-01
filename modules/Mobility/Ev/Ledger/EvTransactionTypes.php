<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Ledger;

use Modules\Banking\Contracts\TransactionTypeRegistry;

enum EvTransactionTypes: string implements TransactionTypeRegistry
{
    case EV_HOLD = 'oto:ev_hold';
    case EV_CAPTURE = 'oto:ev_capture';
    case EV_RELEASE = 'oto:ev_release';
}
