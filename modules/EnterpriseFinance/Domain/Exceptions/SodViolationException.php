<?php

declare(strict_types=1);

namespace Modules\EnterpriseFinance\Domain\Exceptions;

use RuntimeException;

class SodViolationException extends RuntimeException
{
    public function __construct(string $ruleCode, string $roleA, string $roleB)
    {
        parent::__construct("Pelanggaran pemisahan tugas (SoD) kritis [{$ruleCode}]: Pengguna memiliki kombinasi peran terlarang '{$roleA}' dan '{$roleB}'.");
    }
}
