<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimRun;

interface SimRunRepositoryInterface
{
    public function findById(string $id): ?SimRun;

    public function findByName(string $name): ?SimRun;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * @return Collection<int, SimRun>
     */
    public function getActiveRuns(): Collection;

    public function save(SimRun $run): bool;

    public function delete(SimRun $run): bool;
}
