<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Egy\Application\Actions\IngestSmartGridMeterTelemetryAction;
use Modules\Egy\Domain\Models\SmartMeter;

class EgyMeterApiController extends Controller
{
    public function ingest(Request $request, IngestSmartGridMeterTelemetryAction $action): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        $meterNumber = (string) $request->input('meter_number');

        // Check authentication / device token
        $user = $request->user();
        if ($user) {
            if (! $user->hasAnyRbacRole(['admin', 'grid_operator', 'energy_manager'])) {
                return response()->json([
                    'message' => 'Forbidden.',
                ], 403);
            }
        } else {
            $deviceToken = $request->header('X-Device-Token');
            if (! $deviceToken) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            $meter = SmartMeter::where('meter_serial_number', $meterNumber)->first();
            if (! $meter || $meter->device_token === null || $meter->device_token !== $deviceToken) {
                return response()->json([
                    'message' => 'Invalid device token.',
                ], 401);
            }
        }

        $validated = $request->validate([
            'meter_number' => ['required', 'string'],
            'cumulative_wh' => ['required', 'integer'],
            'reading_at' => ['nullable', 'string'],
            'is_meter_reset' => ['nullable', 'boolean'],
        ]);

        $reading = $action->execute([
            'meter_number' => $validated['meter_number'],
            'cumulative_wh' => (int) $validated['cumulative_wh'],
            'reading_at' => $validated['reading_at'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'is_meter_reset' => (bool) ($validated['is_meter_reset'] ?? false),
        ]);

        return response()->json([
            'id' => $reading->id,
            'meter_id' => $reading->meter_id,
            'cumulative_wh' => $reading->cumulative_wh,
            'delta_wh' => $reading->delta_wh,
            'interval_status' => $reading->interval_status,
            'reading_at' => $reading->reading_at?->toIso8601String(),
        ], 200);
    }
}
