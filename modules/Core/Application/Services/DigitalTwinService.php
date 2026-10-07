<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimTwinState;

class DigitalTwinService implements DigitalTwinInterface
{
    public function __construct(
        protected SimClockInterface $simClock
    ) {}

    public function recordState(
        string $entityType,
        string $entityId,
        array $state,
        bool $isSandbox = false
    ): SimTwinState {
        return DB::transaction(function () use ($entityType, $entityId, $state, $isSandbox) {
            $latest = SimTwinState::where('entity_type', $entityType)
                ->where('entity_id', $entityId)
                ->where('is_sandbox', $isSandbox)
                ->orderBy('id', 'desc')
                ->first();

            ksort($state);
            $canonicalState = json_encode($state, JSON_THROW_ON_ERROR);

            // Idempotency: if latest record already has identical state, return latest
            if ($latest) {
                $latestState = $latest->state;
                ksort($latestState);
                if (json_encode($latestState, JSON_THROW_ON_ERROR) === $canonicalState) {
                    return $latest;
                }
            }

            $prevHash = $latest ? $latest->state_hash : 'GENESIS';
            $stateHash = hash('sha256', "{$prevHash}|{$entityType}|{$entityId}|{$canonicalState}");

            return SimTwinState::create([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'state' => $state,
                'state_hash' => $stateHash,
                'prev_hash' => $prevHash,
                'valid_from' => $this->simClock->now(),
                'is_sandbox' => $isSandbox,
            ]);
        });
    }

    public function getLatestState(
        string $entityType,
        string $entityId,
        bool $isSandbox = false
    ): ?SimTwinState {
        return SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('is_sandbox', $isSandbox)
            ->orderBy('id', 'desc')
            ->first();
    }

    public function verifyChain(string $entityType, string $entityId, bool $isSandbox = false): bool
    {
        $states = SimTwinState::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('is_sandbox', $isSandbox)
            ->orderBy('id', 'asc')
            ->get();

        $expectedPrev = 'GENESIS';
        foreach ($states as $s) {
            if ($s->prev_hash !== $expectedPrev) {
                return false;
            }

            $state = $s->state;
            ksort($state);
            $canonical = json_encode($state, JSON_THROW_ON_ERROR);
            $expectedHash = hash('sha256', "{$expectedPrev}|{$entityType}|{$entityId}|{$canonical}");

            if ($s->state_hash !== $expectedHash) {
                return false;
            }

            $expectedPrev = $s->state_hash;
        }

        return true;
    }
}
