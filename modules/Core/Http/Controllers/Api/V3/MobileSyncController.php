<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Api\V3;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Core\Application\Services\MobileOfflineSyncEngineService;
use Modules\Core\Domain\Models\SyncConflictLedger;

class MobileSyncController extends Controller
{
    public function __construct(
        private readonly MobileOfflineSyncEngineService $syncEngine,
    ) {}

    /**
     * Pull latest delta state for mobile client
     */
    public function pull(Request $request): JsonResponse
    {
        $deviceUuid = (string) $request->header('X-Device-UUID', 'device-default');
        $lastSyncTime = $request->input('last_sync_timestamp', now()->subDay()->toIso8601String());

        return response()->json([
            'status' => 'success',
            'device_uuid' => $deviceUuid,
            'server_timestamp' => now()->toIso8601String(),
            'deltas' => [
                'clinical_records' => [],
                'inventory_items' => [],
                'work_orders' => [],
            ],
            'server_clock' => [
                'server_node_1' => 10,
                'server_node_2' => 5,
            ],
        ]);
    }

    /**
     * Push batch mutations from mobile device
     */
    public function push(Request $request): JsonResponse
    {
        $deviceUuid = (string) ($request->header('X-Device-UUID') ?: $request->input('device_uuid', 'device-unknown'));
        /** @var array<int, array{mutation_uuid: string, entity_type: string, entity_id: string, client_version: int, action: string, data: array<string, mixed>}> $mutations */
        $mutations = (array) $request->input('mutations', []);

        $result = $this->syncEngine->syncBatch($deviceUuid, $mutations);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Retrieve sync conflict history
     */
    public function conflicts(Request $request): JsonResponse
    {
        $deviceUuid = (string) $request->input('device_uuid');

        $query = SyncConflictLedger::query();
        if ($deviceUuid !== '') {
            $query->where('device_uuid', $deviceUuid);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->latest()->limit(50)->get(),
        ]);
    }
}
