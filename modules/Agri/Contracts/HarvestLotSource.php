<?php

declare(strict_types=1);

namespace Modules\Agri\Contracts;

interface HarvestLotSource
{
    /**
     * @return array<string, mixed>|null
     */
    public function describe(string|int $collectionBatchId): ?array;

    public function availableGrams(string|int $collectionBatchId): int;

    public function consume(string|int $collectionBatchId, int $grams, string $idempotencyKey): void;
}
