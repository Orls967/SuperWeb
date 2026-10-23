<?php

declare(strict_types=1);

namespace Modules\Banking\Contracts;

use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Domain\Models\LedgerTransaction;

interface Ledger
{
    public function post(PostingDTO $dto): LedgerTransaction;

    public function getCachedBalance(string $code): ?string;

    public function findAccountByCode(string $code): ?LedgerAccount;
}
