<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Exceptions;

use DomainException;

class InvalidStateTransition extends DomainException
{
    public static function fromTo(string|object $from, string|object $to, string $entity = ''): self
    {
        $fromStr = is_object($from) ? ($from->value ?? get_class($from)) : (string) $from;
        $toStr = is_object($to) ? ($to->value ?? get_class($to)) : (string) $to;
        $entityPrefix = $entity !== '' ? "[{$entity}] " : '';

        return new self("{$entityPrefix}Invalid state transition from '{$fromStr}' to '{$toStr}'.");
    }
}
