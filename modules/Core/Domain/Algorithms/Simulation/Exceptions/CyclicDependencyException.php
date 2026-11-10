<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Algorithms\Simulation\Exceptions;

use RuntimeException;

class CyclicDependencyException extends RuntimeException
{
    /**
     * @param  list<string>  $cycleNodes
     */
    public function __construct(public readonly array $cycleNodes)
    {
        $nodesStr = implode(', ', $cycleNodes);
        parent::__construct("CYCLE: Ditemukan siklus dependensi pada simpul-simpul berikut: [{$nodesStr}].");
    }
}
