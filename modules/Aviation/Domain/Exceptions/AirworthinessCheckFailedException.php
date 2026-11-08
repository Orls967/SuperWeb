<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Exceptions;

use RuntimeException;

final class AirworthinessCheckFailedException extends RuntimeException
{
    /**
     * @param list<string> $reasons
     */
    public static function withReasons(string $registration, array $reasons): self
    {
        $details = implode(', ', $reasons);
        return new self("Pesawat {$registration} gagal uji kelaikan dispatch: [{$details}].");
    }
}

