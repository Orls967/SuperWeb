<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;

interface ClockInterface
{
    /**
     * Get the current wall-clock or virtual simulation timestamp.
     */
    public function now(): CarbonInterface;
}
