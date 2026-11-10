<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Core\Domain\Models\SimRun;
use Modules\Core\Domain\Repositories\SimRunRepositoryInterface;

final class SimRunEloquentRepository implements SimRunRepositoryInterface
{
    public function findById(string $id): ?SimRun
    {
        return SimRun::find($id);
    }

    public function findByName(string $name): ?SimRun
    {
        return SimRun::where('name', $name)->first();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SimRun::query()->orderBy('created_at', 'desc');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['mode'])) {
            $query->where('mode', $filters['mode']);
        }

        return $query->paginate($perPage);
    }

    public function getActiveRuns(): Collection
    {
        return SimRun::where('status', 'running')->get();
    }

    public function save(SimRun $run): bool
    {
        return $run->save();
    }

    public function delete(SimRun $run): bool
    {
        return (bool) $run->delete();
    }
}
