<?php

declare(strict_types=1);

namespace Modules\Intercompany\Domain\Exceptions;

use RuntimeException;

class IntercompanyMakerCheckerViolationException extends RuntimeException
{
    public function __construct(int $operatorId, string $action = 'menyetujui transaksi yang dibuat sendiri')
    {
        parent::__construct("Pelanggaran prinsip Maker-Checker: Pengguna [ID: {$operatorId}] dilarang {$action}.");
    }
}
