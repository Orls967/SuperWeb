<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class ImmutableRecordException extends RuntimeException
{
    public function __construct(string $model, string $action)
    {
        parent::__construct("Model {$model} bersifat append-only dan tidak boleh di-{$action}.");
    }
}
