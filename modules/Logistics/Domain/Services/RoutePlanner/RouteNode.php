<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Services\RoutePlanner;

class RouteNode
{
    public function __construct(
        public readonly int $locationId,
        public readonly string $code,
        public readonly string $name,
        public readonly int $minConnectionMinutes = 60
    ) {}
}
