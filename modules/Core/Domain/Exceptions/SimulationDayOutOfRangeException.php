<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RangeException;

final class SimulationDayOutOfRangeException extends RangeException
{
    public function __construct(int $requestedDay, int $maxAllowedDay)
    {
        parent::__construct("Hari simulasi {$requestedDay} berada di luar batas yang diizinkan (maksimum {$maxAllowedDay}).");
    }
}
