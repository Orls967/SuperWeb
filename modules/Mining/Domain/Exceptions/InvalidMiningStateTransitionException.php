<?php

declare(strict_types=1);

namespace Modules\Mining\Domain\Exceptions;

use DomainException;

final class InvalidMiningStateTransitionException extends DomainException
{
    public static function forEntity(string $entity, string $from, string $to, string $reason = ''): self
    {
        $msg = "Transisi status tidak sah untuk entitas [{$entity}] dari '{$from}' ke '{$to}'";
        if ($reason !== '') {
            $msg .= ": {$reason}";
        }

        return new self($msg);
    }
}

