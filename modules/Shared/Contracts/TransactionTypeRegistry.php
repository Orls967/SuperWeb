<?php

declare(strict_types=1);

namespace Modules\Shared\Contracts;

interface TransactionTypeRegistry
{
    /**
     * @return list<static>
     */
    public static function cases(): array;
}
