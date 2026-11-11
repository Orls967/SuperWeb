<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Contract for owner modules accepting streaming row-by-row bulk imports in 500-row batches.
 */
interface BulkImportTarget
{
    public function targetKey(): string;

    public function moduleName(): string;

    public function label(): string;

    /**
     * Required CSV/XLSX header column names.
     *
     * @return list<string>
     */
    public function requiredColumns(): array;

    /**
     * Validate and normalize one streaming row without writing to DB.
     *
     * @param  array<string, string>  $row
     * @return array{valid: bool, reason: string|null, normalized: array<string, mixed>}
     */
    public function validateRow(array $row, int $lineNumber): array;

    /**
     * Persist one validated chunk idempotently inside a single database transaction.
     *
     * @param  list<array<string, mixed>>  $normalizedRows
     */
    public function applyBatch(array $normalizedRows, string $idempotencyKey, string $tenantId): int;

    /**
     * Stream rows for async ZIP export in bounded chunks matching filters.
     *
     * @return iterable<int, array<string, scalar|null>>
     */
    public function exportRows(int $maxRows = 50000): iterable;
}
