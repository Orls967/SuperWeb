<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Contract for owner modules registering historical (> 5 years) archive targets (W8-D52).
 */
interface RetentionArchiveProvider
{
    public function moduleName(): string;

    public function sourceTable(): string;

    public function archiveTable(): string;

    public function timestampColumn(): string;

    public function primaryKeyColumn(): string;

    /**
     * Columns ordered canonically to compute deterministic SHA-256 chunk verification digests.
     *
     * @return list<string>
     */
    public function hashColumns(): array;
}

