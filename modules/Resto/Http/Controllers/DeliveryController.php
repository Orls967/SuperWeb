<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Actions\CreateDeliveryOrderAction;
use Modules\Resto\Application\Actions\FailDeliveryAction;
use Modules\Resto\Domain\Enums\DeliveryStatus;
use Modules\Resto\Domain\Models\Delivery;
use Modules\Resto\Domain\Models\MenuItem;
use Modules\Resto\Domain\Models\Outlet;

class DeliveryController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $deliveries = Delivery::with(['order.items', 'outlet'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->latest('id')
            ->paginate(15);

        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::deliveries.index', [
            'deliveries' => $deliveries,
            'outlets' => $outlets,
            'selectedStatus' => $status,
            'selectedOutletId' => $outletId ? (int) $outletId : null,
        ]);
    }

    public function create(): View
    {
        $outlets = Outlet::where('is_active', true)->get();
        $menuItems = MenuItem::where('is_active', true)->with('category')->get();

        return view('resto::deliveries.create', [
            'outlets' => $outlets,
            'menuItems' => $menuItems,
        ]);
    }

    public function store(Request $request, CreateDeliveryOrderAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'recipient_name' => ['required', 'string', 'max:100'],
            'recipient_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['required', 'string'],
            'distance_km' => ['required', 'numeric', 'min:0.1', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:resto_menus,id'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:wallet,cash'],
        ]);

        $res = $action->handle(
            outletId: (int) $validated['outlet_id'],
            itemsData: $validated['items'],
            recipientName: $validated['recipient_name'],
            recipientPhone: $validated['recipient_phone'],
            deliveryAddress: $validated['delivery_address'],
            distanceKm: (float) $validated['distance_km'],
            customerUser: $request->user(),
            paymentMethod: $validated['payment_method']
        );

        return redirect()->route('resto.deliveries.show', $res['delivery'])
            ->with('success', "Pesanan delivery #{$res['order']->number} berhasil dibuat dan sedang dipersiapkan di dapur.");
    }

    public function show(Delivery $delivery): View
    {
        $delivery->load(['order.items.menuItem', 'outlet']);

        return view('resto::deliveries.show', [
            'delivery' => $delivery,
        ]);
    }

    public function updateStatus(Request $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:on_the_way,delivered'],
            'courier_name' => ['nullable', 'string', 'max:100'],
            'courier_phone' => ['nullable', 'string', 'max:30'],
            'tracking_number' => ['nullable', 'string', 'max:50'],
        ]);

        $delivery->status = DeliveryStatus::from($validated['status']);
        if (! empty($validated['courier_name'])) {
            $delivery->courier_name = $validated['courier_name'];
        }
        if (! empty($validated['courier_phone'])) {
            $delivery->courier_phone = $validated['courier_phone'];
        }
        if (! empty($validated['tracking_number'])) {
            $delivery->tracking_number = $validated['tracking_number'];
        }

        if ($delivery->status === DeliveryStatus::ON_THE_WAY && ! $delivery->shipped_at) {
            $delivery->shipped_at = now();
        } elseif ($delivery->status === DeliveryStatus::DELIVERED && ! $delivery->delivered_at) {
            $delivery->delivered_at = now();
        }

        $delivery->save();

        return back()->with('success', "Status delivery diperbarui menjadi: {$delivery->status->label()}");
    }

    public function fail(Request $request, Delivery $delivery, FailDeliveryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $action->handle(
            delivery: $delivery,
            failureReason: $validated['reason'],
            refundFoodOnly: true
        );

        return back()->with('success', 'Pengiriman ditandai gagal. Refund harga makanan telah diproses kembali ke saldo dompet pelanggan.');
    }
}
