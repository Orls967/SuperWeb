<?php

declare(strict_types=1);

namespace Modules\Circular\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Circular\Application\Actions\CloseAuctionRoundAction;
use Modules\Circular\Application\Actions\CreateAuctionRoundAction;
use Modules\Circular\Application\Actions\CreateListingAction;
use Modules\Circular\Application\Actions\PlaceOrderAction;
use Modules\Circular\Application\Actions\RecordOrderPlantReceiptAction;
use Modules\Circular\Application\Actions\SettleOrderAction;
use Modules\Circular\Application\Actions\SubmitBidAction;
use Modules\Circular\Domain\Models\CirAuctionRound;
use Modules\Circular\Domain\Models\CirBid;
use Modules\Circular\Domain\Models\CirListing;
use Modules\Circular\Domain\Models\CirOrder;

final class CircularMarketplaceController extends Controller
{
    public function listingsIndex(Request $request): View
    {
        $user = $request->user();
        $query = CirListing::query()->orderByDesc('id');

        if ($user !== null && $user->role !== 'admin') {
            $query->where('seller_party_id', $user->id);
        }

        $listings = $query->limit(50)->get();

        return view('circular::listings', [
            'listings' => $listings,
        ]);
    }

    public function storeListing(Request $request, CreateListingAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'material_code' => ['required', 'string', 'max:64'],
            'grade_code' => ['nullable', 'string', 'max:32'],
            'available_grams' => ['required', 'integer', 'min:1'],
            'unit_price_per_kg_idr' => ['required', 'integer', 'min:1'],
            'mode' => ['nullable', 'in:fixed,auction'],
            'auction_round_id' => ['nullable', 'integer'],
            'origin_location_id' => ['nullable', 'integer'],
            'idempotency_key' => ['nullable', 'string', 'max:128'],
        ]);

        $listing = $action->execute([
            'seller_party_id' => (int) $request->user()->id,
            'auction_round_id' => isset($validated['auction_round_id']) ? (int) $validated['auction_round_id'] : null,
            'material_code' => (string) $validated['material_code'],
            'grade_code' => (string) ($validated['grade_code'] ?? 'A'),
            'available_grams' => (int) $validated['available_grams'],
            'unit_price_per_kg_idr' => (int) $validated['unit_price_per_kg_idr'],
            'mode' => (string) ($validated['mode'] ?? 'fixed'),
            'origin_location_id' => isset($validated['origin_location_id']) ? (int) $validated['origin_location_id'] : null,
            'idempotency_key' => $validated['idempotency_key'] ?? $request->header('Idempotency-Key'),
        ], $request->user());

        if ($request->wantsJson()) {
            return response()->json(['data' => $listing], 201);
        }

        return redirect()->route('circular.listings.index')->with('status', 'Listing berhasil diterbitkan.');
    }

    public function marketIndex(Request $request): View
    {
        $listings = CirListing::where('status', 'active')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $orders = CirOrder::where('buyer_party_id', $request->user()?->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('circular::market', [
            'listings' => $listings,
            'orders' => $orders,
        ]);
    }

    public function storeOrder(Request $request, PlaceOrderAction $action): JsonResponse|RedirectResponse
    {
        $idempotencyKey = $request->input('idempotency_key') ?? $request->header('Idempotency-Key');

        $validated = $request->validate([
            'listing_id' => ['required', 'integer'],
            'ordered_grams' => ['required', 'integer', 'min:1'],
            'destination_facility_id' => ['nullable', 'integer'],
        ]);

        $order = $action->execute([
            'listing_id' => (int) $validated['listing_id'],
            'buyer_party_id' => (int) $request->user()->id,
            'ordered_grams' => (int) $validated['ordered_grams'],
            'destination_facility_id' => isset($validated['destination_facility_id']) ? (int) $validated['destination_facility_id'] : null,
            'idempotency_key' => is_string($idempotencyKey) ? $idempotencyKey : '',
        ], $request->user());

        if ($request->wantsJson()) {
            return response()->json(['data' => $order], 201);
        }

        return redirect()->route('circular.market.index')->with('status', 'Order berhasil dibuat.');
    }

    public function showOrder(Request $request, CirOrder $order): JsonResponse|View
    {
        $user = $request->user();
        $isOwner = $user !== null && (
            $user->role === 'admin'
            || $user->role === 'circular_admin'
            || (int) $order->buyer_party_id === (int) $user->id
            || (int) $order->seller_party_id === (int) $user->id
        );

        if (! $isOwner) {
            abort(403, 'Anda tidak memiliki akses ke pesanan privat pihak lain.');
        }

        if ($request->wantsJson()) {
            return response()->json(['data' => $order->load(['lines', 'settlement'])]);
        }

        return view('circular::order-detail', [
            'order' => $order->load(['lines', 'settlement']),
        ]);
    }

    public function showBid(Request $request, CirBid $bid): JsonResponse
    {
        $user = $request->user();
        $isOwner = $user !== null && (
            $user->role === 'admin'
            || $user->role === 'circular_admin'
            || (int) $bid->buyer_party_id === (int) $user->id
        );

        if (! $isOwner) {
            abort(403, 'Penawaran lelang tertutup milik pihak lain tidak dapat diakses.');
        }

        return response()->json(['data' => $bid]);
    }

    public function auctionsIndex(): View
    {
        $rounds = CirAuctionRound::withCount(['listings', 'bids', 'orders'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return view('circular::auctions', [
            'rounds' => $rounds,
        ]);
    }

    public function storeAuctionRound(Request $request, CreateAuctionRoundAction $action): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'material_code' => ['required', 'string', 'max:64'],
            'grade_code' => ['nullable', 'string', 'max:32'],
            'origin_location_id' => ['nullable', 'integer'],
            'destination_facility_id' => ['nullable', 'integer'],
        ]);

        $round = $action->execute([
            'material_code' => (string) $validated['material_code'],
            'grade_code' => (string) ($validated['grade_code'] ?? 'A'),
            'origin_location_id' => isset($validated['origin_location_id']) ? (int) $validated['origin_location_id'] : null,
            'destination_facility_id' => isset($validated['destination_facility_id']) ? (int) $validated['destination_facility_id'] : null,
            'idempotency_key' => $request->input('idempotency_key') ?? $request->header('Idempotency-Key'),
        ], $request->user());

        if ($request->wantsJson()) {
            return response()->json(['data' => $round], 201);
        }

        return redirect()->route('circular.auctions.index')->with('status', 'Ronde lelang dibuka.');
    }

    public function submitBid(Request $request, SubmitBidAction $action): JsonResponse
    {
        $validated = $request->validate([
            'auction_round_id' => ['required', 'integer'],
            'quantity_grams' => ['required', 'integer', 'min:1'],
            'max_price_per_kg_idr' => ['required', 'integer', 'min:1'],
        ]);

        $bid = $action->execute([
            'auction_round_id' => (int) $validated['auction_round_id'],
            'buyer_party_id' => (int) $request->user()->id,
            'quantity_grams' => (int) $validated['quantity_grams'],
            'max_price_per_kg_idr' => (int) $validated['max_price_per_kg_idr'],
            'idempotency_key' => $request->input('idempotency_key') ?? $request->header('Idempotency-Key'),
        ], $request->user());

        return response()->json(['data' => $bid], 201);
    }

    public function closeRound(Request $request, CirAuctionRound $round, CloseAuctionRoundAction $action): JsonResponse|RedirectResponse
    {
        $key = (string) ($request->input('idempotency_key') ?? $request->header('Idempotency-Key') ?? "close:{$round->id}");
        $closed = $action->execute($round, $key, $request->user());

        if ($request->wantsJson()) {
            return response()->json(['data' => $closed]);
        }

        return redirect()->route('circular.auctions.index')->with('status', 'Ronde lelang berhasil ditutup.');
    }

    public function recordReceipt(Request $request, CirOrder $order, RecordOrderPlantReceiptAction $action): JsonResponse
    {
        $validated = $request->validate([
            'accepted_grams' => ['required', 'integer', 'min:1'],
            'grade_code' => ['nullable', 'string', 'max:32'],
        ]);

        $key = (string) ($request->input('idempotency_key') ?? $request->header('Idempotency-Key') ?? "receipt:{$order->id}");
        $updated = $action->execute(
            order: $order,
            acceptedGrams: (int) $validated['accepted_grams'],
            gradeCode: (string) ($validated['grade_code'] ?? $order->grade_code),
            idempotencyKey: $key,
            actor: $request->user()
        );

        return response()->json(['data' => $updated]);
    }

    public function settleOrder(Request $request, CirOrder $order, SettleOrderAction $action): JsonResponse
    {
        $key = (string) ($request->input('idempotency_key') ?? $request->header('Idempotency-Key') ?? '');
        $settlement = $action->execute($order, $key);

        return response()->json(['data' => $settlement]);
    }
}
