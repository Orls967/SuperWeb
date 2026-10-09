<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Logistics\Application\Actions\BookShipmentAction;
use Modules\Logistics\Application\Actions\QuoteShipmentAction;
use Modules\Logistics\Domain\Enums\ServiceLevel;
use Modules\Logistics\Domain\Models\Quote;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\TrackingEvent;

/**
 * API v1 Logistics — Quotes, Shipments (Idempotency-Key), Tracking.
 *
 * Authentication: Laravel Sanctum token with abilities.
 * Rate limit: 60 req/min per token.
 */
class LogisticsApiController extends Controller
{
    /**
     * POST /api/v1/logistics/quotes
     * Membuat quote ongkir baru.
     *
     * Token ability: quote:create
     */
    public function createQuote(Request $request, QuoteShipmentAction $action): JsonResponse
    {
        if (! $request->user()->tokenCan('quote:create')) {
            return response()->json(['message' => 'Token ability [quote:create] required.'], 403);
        }

        $request->validate([
            'origin_location_id' => 'required|integer|exists:lgx_locations,id',
            'destination_location_id' => 'required|integer|exists:lgx_locations,id',
            'service_level' => 'required|string|in:regular,express,same_day',
            'packages' => 'required|array|min:1',
            'packages.*.weight_g' => 'required|integer|min:1',
            'packages.*.length_mm' => 'required|integer|min:1',
            'packages.*.width_mm' => 'required|integer|min:1',
            'packages.*.height_mm' => 'required|integer|min:1',
            'packages.*.description' => 'nullable|string|max:200',
            'declared_value_idr' => 'nullable|integer|min:0',
            'insured' => 'nullable|boolean',
            'cod_amount_idr' => 'nullable|integer|min:0',
        ]);

        $quote = $action->execute(
            shipper: $request->user(),
            originLocationId: $request->integer('origin_location_id'),
            destinationLocationId: $request->integer('destination_location_id'),
            serviceLevel: ServiceLevel::from($request->input('service_level')),
            packages: $request->input('packages'),
            declaredValueIdr: $request->integer('declared_value_idr', 0),
            insured: $request->boolean('insured', false),
            codAmountIdr: $request->integer('cod_amount_idr', 0),
        );

        return response()->json([
            'data' => [
                'quote_id' => $quote->id,
                'tracking_number_preview' => null,
                'total_amount_idr' => $quote->total_amount_idr,
                'chargeable_weight_g' => $quote->chargeable_weight_g,
                'breakdown' => $quote->breakdown ?? [],
                'valid_until' => $quote->valid_until?->toIso8601String(),
                'hash' => $quote->hash,
            ],
        ], 201);
    }

    /**
     * POST /api/v1/logistics/shipments
     * Booking shipment baru (idempoten via Idempotency-Key header).
     *
     * Token ability: shipment:create
     */
    public function createShipment(Request $request, BookShipmentAction $action): JsonResponse
    {
        if (! $request->user()->tokenCan('shipment:create')) {
            return response()->json(['message' => 'Token ability [shipment:create] required.'], 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        // Check for existing shipment with same idempotency key
        if ($idempotencyKey) {
            $existing = Shipment::where('source_type', 'api_idempotency')
                ->where('source_id', $idempotencyKey)
                ->first();
            if ($existing) {
                return response()->json([
                    'data' => $this->formatShipment($existing),
                    'meta' => ['idempotent_replay' => true],
                ], 200);
            }
        }

        $request->validate([
            'quote_id' => 'required|integer|exists:lgx_quotes,id',
            'consignee_name' => 'required|string|max:100',
            'consignee_phone' => 'required|string|max:20',
            'consignee_address' => 'required|array',
            'consignee_address.street' => 'required|string',
            'consignee_address.city' => 'required|string',
            'pin' => 'required|string|size:6',
        ]);

        $quote = Quote::findOrFail($request->integer('quote_id'));

        $shipment = $action->execute(
            shipper: $request->user(),
            quote: $quote,
            consigneeName: $request->input('consignee_name'),
            consigneePhone: $request->input('consignee_phone'),
            consigneeAddress: $request->input('consignee_address'),
            pin: $request->input('pin'),
        );

        // Tag with idempotency key
        if ($idempotencyKey) {
            $shipment->update([
                'source_type' => 'api_idempotency',
                'source_id' => $idempotencyKey,
            ]);
        }

        return response()->json([
            'data' => $this->formatShipment($shipment->fresh()),
        ], 201);
    }

    /**
     * GET /api/v1/logistics/shipments/{tracking_number}
     * Ambil detail shipment.
     *
     * Token ability: shipment:read
     */
    public function showShipment(Request $request, string $trackingNumber): JsonResponse
    {
        if (! $request->user()->tokenCan('shipment:read')) {
            return response()->json(['message' => 'Token ability [shipment:read] required.'], 403);
        }

        $shipment = Shipment::where('tracking_number', $trackingNumber)
            ->where('shipper_id', $request->user()->id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->formatShipment($shipment),
        ]);
    }

    /**
     * GET /api/v1/logistics/shipments
     * List shipments (cursor paginated).
     *
     * Token ability: shipment:read
     */
    public function listShipments(Request $request): JsonResponse
    {
        if (! $request->user()->tokenCan('shipment:read')) {
            return response()->json(['message' => 'Token ability [shipment:read] required.'], 403);
        }

        $query = Shipment::where('shipper_id', $request->user()->id)
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginated = $query->cursorPaginate(50);

        return response()->json([
            'data' => $paginated->map(fn ($s) => $this->formatShipment($s)),
            'meta' => [
                'next_cursor' => $paginated->nextCursor()?->encode(),
                'has_more' => $paginated->hasMorePages(),
            ],
        ]);
    }

    /**
     * GET /api/v1/logistics/tracking/{tracking_number}
     * Pelacakan publik (throttled, PII masked).
     *
     * No authentication required; rate limited 30/min.
     */
    public function track(string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::where('tracking_number', $trackingNumber)->first();

        if (! $shipment) {
            return response()->json(['error' => 'Resi tidak ditemukan.'], 404);
        }

        $events = $shipment->trackingEvents()
            ->with('location')
            ->orderByDesc('sequence')
            ->limit(50)
            ->get()
            ->map(fn (TrackingEvent $te) => [
                'event_type' => $te->event_type,
                'description' => $te->description,
                'location' => $te->location?->name,
                'occurred_at' => $te->occurred_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'tracking_number' => $shipment->tracking_number,
                'status' => $shipment->status->value,
                'status_label' => $shipment->status->label(),
                'origin' => $shipment->origin?->name,
                'destination' => $shipment->destination?->name,
                'booked_at' => $shipment->booked_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                'events' => $events,
            ],
        ]);
    }

    private function formatShipment(Shipment $s): array
    {
        return [
            'tracking_number' => $s->tracking_number,
            'status' => $s->status->value,
            'status_label' => $s->status->label(),
            'service_level' => $s->service_level?->value,
            'mode' => $s->mode?->value,
            'total_amount_idr' => $s->total_amount_idr,
            'consignee_name' => $s->consignee_name,
            'origin_location_id' => $s->origin_location_id,
            'destination_location_id' => $s->destination_location_id,
            'booked_at' => $s->booked_at?->toIso8601String(),
            'picked_up_at' => $s->picked_up_at?->toIso8601String(),
            'delivered_at' => $s->delivered_at?->toIso8601String(),
        ];
    }
}
