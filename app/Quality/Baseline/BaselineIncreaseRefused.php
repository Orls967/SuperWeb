<?php

declare(strict_types=1);

namespace App\Quality\Baseline;

use RuntimeException;

/**
 * Thrown when a baseline update would add violations without a DECISIONS.md reference.
 */
final class BaselineIncreaseRefused extends RuntimeException
{
    public function __construct(public readonly BaselineComparison $comparison)
    {
        parent::__construct(
            "Baseline tidak boleh naik tanpa rujukan DECISIONS.md (--allow-new=\"DECISIONS.md#…\"):\n".$comparison->describe()
        );
    }
}
