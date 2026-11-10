<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Illuminate\Support\Facades\DB;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Contracts\SimClockInterface;
use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

class DigitalTwinService implements DigitalTwinInterface
{
    public function __construct(
        protected SimClockInterface $simClock
    ) {}

    /**
     * Alias untuk recordState agar kompatibel dengan pemanggilan fleksibel.
     *
     * @param  array<string, mixed>  $state
     */
    public function record(
        string $entityType,
        string $entityId,
        array $state,
        bool $isSandbox = false
    ): SimTwinState {
        return $this->recordState($entityType, $entityId, $state, $isSandbox);
    }

    /**
     * Record a new state for a digital twin with hash-chaining.
     *
     * @param  array<string, mixed>  $state
     */
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

    /**
     * Mengambil snapshot seluruh digital twin aktif pada hari tertentu untuk sesi simulasi.
     */
    public function takeSnapshot(string $runId, int $dayNumber, bool $isSandbox = false): int
    {
        $latestStates = SimTwinState::where('is_sandbox', $isSandbox)
            ->orderBy('id', 'desc')
            ->get()
            ->unique(fn ($item) => "{$item->entity_type}:{$item->entity_id}");

        $count = 0;
        foreach ($latestStates as $state) {
            SimTwinSnapshot::updateOrCreate(
                [
                    'run_id' => $runId,
                    'day_number' => $dayNumber,
                    'entity_type' => $state->entity_type,
                    'entity_id' => $state->entity_id,
                ],
                [
                    'state_hash' => $state->state_hash,
                    'state_payload' => $state->state,
                    'created_at' => now(),
                ]
            );
            $count++;
        }

        return $count;
    }
}
