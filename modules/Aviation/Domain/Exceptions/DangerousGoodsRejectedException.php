<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Exceptions;

use RuntimeException;

final class DangerousGoodsRejectedException extends RuntimeException
{
    /**
     * @param list<string> $violations
     */
    public static function withViolations(string $unNumber, array $violations): self
    {
        $details = implode(', ', $violations);
        return new self("Muatan kargo nomor PBB {$unNumber} ditolak regulasi DG: [{$details}].");
    }
}

