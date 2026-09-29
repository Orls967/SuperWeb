<?php

declare(strict_types=1);

namespace Modules\Shared\Application;

use Illuminate\Support\Facades\DB;

abstract class BaseAction
{
    /**
     * Run a callback inside a DB transaction with retry logic.
     */
    protected function transaction(callable $callback, int $attempts = 3): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
