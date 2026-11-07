<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Routing;

final readonly class HousekeepingRouteCertificate
{
    /**
     * @param  array<string, list<int>>  $assignments
     * @param  array<string, list<int>>  $routes
     * @param  array<string, int>  $workloads
     * @param  list<int>  $unassignedQueue
     */
    public function __construct(
        public array $assignments,
        public array $routes,
        public array $workloads,
        public array $unassignedQueue,
        public int $creditLimit,
        public int $timestamp
    ) {}
}
