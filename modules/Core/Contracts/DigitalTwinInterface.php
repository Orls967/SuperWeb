<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Domain\Models\SimTwinState;

interface DigitalTwinInterface
{
    /**
     * Record a new state for a digital twin with hash-chaining.
     */
    public function recordState(
        string $entityType,
        string $entityId,
        array $state,
        bool $isSandbox = false
    ): SimTwinState;

    /**
     * Get the latest verified twin state.
     */
    public function getLatestState(
        string $entityType,
        string $entityId,
        bool $isSandbox = false
    ): ?SimTwinState;

    /**
     * Verify the integrity of the hash chain for a specific twin entity.
     */
    public function verifyChain(string $entityType, string $entityId, bool $isSandbox = false): bool;
}
