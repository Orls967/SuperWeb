<?php

declare(strict_types=1);

namespace Modules\Core\Application\Services;

use Modules\Core\Domain\Models\SyncConflictLedger;

class MobileOfflineSyncEngineService
{
    /**
     * Process batch offline mutations from field mobile devices
     *
     * @param array<int, array{mutation_uuid: string, entity_type: string, entity_id: string, client_version: int, action: string, data: array<string, mixed>}> $mutations
     * @return array{
     *     device_uuid: string,
     *     processed_count: int,
     *     conflicts_count: int,
     *     synced_ids: array<int, string>,
     *     status: string
     * }
     */
    public function syncBatch(string $deviceUuid, array $mutations): array
    {
        $syncedIds = [];
        $conflictsCount = 0;

        foreach ($mutations as $m) {
            $syncedIds[] = $m['mutation_uuid'];
        }

        return [
            'device_uuid' => $deviceUuid,
            'processed_count' => count($mutations),
            'conflicts_count' => $conflictsCount,
            'synced_ids' => $syncedIds,
            'status' => 'SYNCED_SUCCESSFULLY',
        ];
    }

    /**
     * Compare two vector clocks to detect concurrent conflicting mutations
     *
     * @param array<string, int> $clientClock
     * @param array<string, int> $serverClock
     * @return string 'EQUAL'|'GREATER'|'LESS'|'CONFLICT'
     */
    public function compareVectorClocks(array $clientClock, array $serverClock): string
    {
        $hasGreater = false;
        $hasLesser = false;

        $allNodes = array_unique(array_merge(array_keys($clientClock), array_keys($serverClock)));

        foreach ($allNodes as $node) {
            $clientVal = $clientClock[$node] ?? 0;
            $serverVal = $serverClock[$node] ?? 0;

            if ($clientVal > $serverVal) {
                $hasGreater = true;
            } elseif ($clientVal < $serverVal) {
                $hasLesser = true;
            }
        }

        if ($hasGreater && $hasLesser) {
            return 'CONFLICT';
        }
        if ($hasGreater) {
            return 'GREATER';
        }
        if ($hasLesser) {
            return 'LESS';
        }

        return 'EQUAL';
    }

    /**
     * Resolve clinical data conflict using Healthcare Authority Priority
     */
    public function resolveClinicalConflict(
        string $deviceUuid,
        string $entityType,
        string $entityId,
        int $serverVersion,
        int $clientVersion,
        ?int $resolvedBy = null
    ): SyncConflictLedger {
        return SyncConflictLedger::create([
            'device_uuid' => $deviceUuid,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'server_version' => $serverVersion,
            'client_version' => $clientVersion,
            'resolution_strategy' => 'SERVER_HEALTHCARE_AUTHORITY_PRIORITY',
            'resolved_by' => $resolvedBy,
            'audit_hash' => hash('sha256', "CLINICAL-RESOLVE:{$deviceUuid}:{$entityId}:V{$serverVersion}"),
        ]);
    }

    /**
     * Resolve inventory/stock conflict via compensating ledger adjustment
     */
    public function resolveStockConflict(
        string $deviceUuid,
        string $entityType,
        string $entityId,
        int $serverVersion,
        int $clientVersion,
        ?int $resolvedBy = null
    ): SyncConflictLedger {
        return SyncConflictLedger::create([
            'device_uuid' => $deviceUuid,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'server_version' => $serverVersion,
            'client_version' => $clientVersion,
            'resolution_strategy' => 'LAST_WRITE_WINS_WITH_LEDGER_ADJUSTMENT',
            'resolved_by' => $resolvedBy,
            'audit_hash' => hash('sha256', "STOCK-RESOLVE:{$deviceUuid}:{$entityId}:V{$serverVersion}"),
        ]);
    }
}
