<?php

declare(strict_types=1);

namespace Modules\Core\Application\Api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CursorPaginationService
{
    public const DEFAULT_LIMIT = 25;

    public const MAX_LIMIT = 100;

    /**
     * @return array{data: Collection, meta: array{limit: int, has_more: bool, next_cursor: string|null, total_returned: int}}
     */
    public function paginate(
        Builder $query,
        int $limit = self::DEFAULT_LIMIT,
        ?string $cursor = null,
        string $sortColumn = 'id',
        string $direction = 'asc',
    ): array {
        $effectiveLimit = max(1, min($limit, self::MAX_LIMIT));
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $operator = $direction === 'asc' ? '>' : '<';

        if ($cursor !== null && trim($cursor) !== '') {
            $decoded = $this->decodeCursor($cursor);
            if ($decoded !== null && isset($decoded['id'])) {
                if ($sortColumn !== 'id' && isset($decoded['val'])) {
                    $query->where(function ($sub) use ($sortColumn, $operator, $decoded): void {
                        $sub->where($sortColumn, $operator, $decoded['val'])
                            ->orWhere(function ($q2) use ($sortColumn, $operator, $decoded): void {
                                $q2->where($sortColumn, '=', $decoded['val'])
                                    ->where('id', $operator, $decoded['id']);
                            });
                    });
                } else {
                    $query->where('id', $operator, $decoded['id']);
                }
            }
        }

        if ($sortColumn !== 'id') {
            $query->orderBy($sortColumn, $direction);
        }
        $query->orderBy('id', $direction);

        // Fetch limit + 1 to detect has_more
        $items = $query->take($effectiveLimit + 1)->get();

        $hasMore = $items->count() > $effectiveLimit;
        if ($hasMore) {
            $items = $items->slice(0, $effectiveLimit)->values();
        }

        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $lastItem = $items->last();
            $cursorData = ['id' => $lastItem->id];
            if ($sortColumn !== 'id') {
                $cursorData['val'] = $lastItem->{$sortColumn};
            }
            $nextCursor = $this->encodeCursor($cursorData);
        }

        return [
            'data' => $items,
            'meta' => [
                'limit' => $effectiveLimit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'total_returned' => $items->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function encodeCursor(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function decodeCursor(string $cursor): ?array
    {
        $remainder = strlen($cursor) % 4;
        if ($remainder) {
            $cursor .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);

        return is_array($data) ? $data : null;
    }
}
