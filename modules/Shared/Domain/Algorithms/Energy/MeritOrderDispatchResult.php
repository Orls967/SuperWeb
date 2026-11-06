<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Algorithms\Energy;

/**
 * Result data object for E2 Energy\MeritOrderDispatch.
 */
final class MeritOrderDispatchResult
{
    /**
     * @param  list<array{
     *     id: string,
     *     capacity_mw: int,
     *     available_capacity_mw: int,
     *     min_stable_mw: int,
     *     marginal_cost: int,
     *     is_must_run: bool,
     *     dispatched_mw: int,
     *     curtailed_mw: int,
     *     cost_thousand_idr: int
     * }>  $allocations
     * @param  array{
     *     load_mw: int,
     *     total_dispatched_mw: int,
     *     total_curtailed_mw: int,
     *     total_cost_thousand_idr: int,
     *     digest: string
     * }  $certificate
     */
    public function __construct(
        public readonly int $loadMw,
        public readonly int $totalDispatchedMw,
        public readonly int $totalCurtailedMw,
        public readonly int $totalCostThousandIdr,
        public readonly array $allocations,
        public readonly array $certificate
    ) {}
}
