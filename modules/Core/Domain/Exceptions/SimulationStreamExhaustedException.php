<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use UnderflowException;

final class SimulationStreamExhaustedException extends UnderflowException
{
    public function __construct(string $streamName)
    {
        parent::__construct("Stream bilangan acak [{$streamName}] telah menghabiskan ruang siklus periode.");
    }
}
