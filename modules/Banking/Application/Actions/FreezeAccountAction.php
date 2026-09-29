<?php

declare(strict_types=1);

namespace Modules\Banking\Application\Actions;

use Modules\Banking\Domain\Models\LedgerAccount;

class FreezeAccountAction
{
    public function execute(LedgerAccount $account, bool $freeze = true): LedgerAccount
    {
        $account->is_frozen = $freeze;
        $account->save();

        return $account;
    }
}
