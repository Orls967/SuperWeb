<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public static function forAction(string $action, string $key): self
    {
        return new self("Konflik idempotensi: aksi [{$action}] dengan kunci [{$key}] telah diproses dengan payload berbeda.");
    }
}
