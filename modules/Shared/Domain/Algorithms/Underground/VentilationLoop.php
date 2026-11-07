<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Underground;

final readonly class VentilationLoop
{
    /**
     * @param  list<array{branch_id: string, direction: int}>  $branches  direction: +1 jika searah jarum jam / loop, -1 jika berlawanan
     */
    public function __construct(
        public string $loopId,
        public array $branches
    ) {}
}
