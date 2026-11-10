<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Ui;

use Illuminate\Http\Request;

/**
 * TableQueryState
 *
 * Mengelola parameter query string untuk komponen x-ds.table:
 * pengurutan kolom, arah sortir, filter pencarian, filter kategori, dan pagination.
 * Mencegah SQL injection dan manipulasi kolom tak diizinkan.
 */
class TableQueryState
{
    private string $sortColumn;

    private string $sortDirection;

    private string $searchQuery;

    private int $page;

    private int $perPage;

    /**
     * @var array<string, mixed>
     */
    private array $filters;

    /**
     * @param list<string> $allowedSortColumns
     * @param array<string, mixed> $allowedFilters
     */
    public function __construct(
        Request $request,
        private readonly array $allowedSortColumns = ['id', 'created_at', 'name', 'status', 'code', 'date'],
        string $defaultSort = 'id',
        string $defaultDirection = 'desc',
        int $defaultPerPage = 15,
        array $allowedFilters = []
    ) {
        $requestedSort = (string) $request->query('sort', $defaultSort);
        $this->sortColumn = in_array($requestedSort, $this->allowedSortColumns, true)
            ? $requestedSort
            : $defaultSort;

        $requestedDir = strtolower((string) $request->query('direction', $defaultDirection));
        $this->sortDirection = in_array($requestedDir, ['asc', 'desc'], true)
            ? $requestedDir
            : $defaultDirection;

        $this->searchQuery = trim((string) $request->query('q', ''));
        $this->page = max(1, (int) $request->query('page', 1));
        $this->perPage = min(100, max(5, (int) $request->query('per_page', $defaultPerPage)));

        $this->filters = [];
        foreach ($allowedFilters as $filterKey => $defaultValue) {
            if ($request->has($filterKey)) {
                $val = $request->query($filterKey);
                if ($val !== null && $val !== '') {
                    $this->filters[$filterKey] = $val;
                }
            }
        }
    }

    public function getSortColumn(): string
    {
        return $this->sortColumn;
    }

    public function getSortDirection(): string
    {
        return $this->sortDirection;
    }

    public function isSortedBy(string $column): bool
    {
        return $this->sortColumn === $column;
    }

    public function getOppositeDirection(string $column): string
    {
        if ($this->sortColumn === $column) {
            return $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }

        return 'asc';
    }

    public function getSearchQuery(): string
    {
        return $this->searchQuery;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFilter(string $key, mixed $default = null): mixed
    {
        return $this->filters[$key] ?? $default;
    }

    /**
     * Membentuk query parameter string dengan modifikasi satu nilai.
     *
     * @param array<string, mixed> $overrides
     */
    public function buildUrlParams(array $overrides = []): string
    {
        $params = array_merge(
            [
                'sort' => $this->sortColumn,
                'direction' => $this->sortDirection,
                'page' => $this->page,
                'per_page' => $this->perPage,
            ],
            $this->searchQuery !== '' ? ['q' => $this->searchQuery] : [],
            $this->filters,
            $overrides
        );

        return http_build_query($params);
    }
}
