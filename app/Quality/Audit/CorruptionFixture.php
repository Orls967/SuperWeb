<?php

declare(strict_types=1);

namespace App\Quality\Audit;

/**
 * Contract for corruption fixtures (KONSEP.md §A14.4).
 *
 * Each audit, reconcile, or verification command must have a corruption fixture
 * that intentionally tampers with one source of truth to prove the audit detects it.
 */
interface CorruptionFixture
{
    /**
     * Artisan command name/signature.
     */
    public function command(): string;

    /**
     * Applies intentional corruption to the database/state.
     *
     * @return string Description or keyword expected in the command failure output.
     */
    public function corrupt(): string;
}
