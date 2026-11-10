<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Exceptions;

use RuntimeException;

class IntercompanyInvalidTransitionException extends RuntimeException
{
    public function __construct(string $fromStatus, string $toStatus)
    {
        parent::__construct("Transisi status transaksi intercompany tidak diizinkan: dari [{$fromStatus}] ke [{$toStatus}].");
    }
}
