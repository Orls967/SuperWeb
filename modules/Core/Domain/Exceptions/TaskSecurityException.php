<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use DomainException;

class TaskSecurityException extends DomainException
{
    public static function unauthorizedMutation(string $taskId, string $userId): self
    {
        return new self(sprintf('Pengguna [%s] tidak memiliki otorisasi untuk memutasi tugas [%s].', $userId, $taskId));
    }

    public static function invalidStateTransition(string $taskId, string $from, string $to): self
    {
        return new self(sprintf('Transisi tugas [%s] dari status [%s] ke [%s] dilarang.', $taskId, $from, $to));
    }
}
