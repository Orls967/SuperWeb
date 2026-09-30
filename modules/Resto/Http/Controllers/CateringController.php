<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Actions\CancelCateringAction;
use Modules\Resto\Application\Actions\CreateCateringOrderAction;
use Modules\Resto\Application\Actions\DeliverAndCompleteCateringAction;
use Modules\Resto\Application\Actions\HoldCateringDepositAction;
use Modules\Resto\Domain\Models\CateringOrder;
use Modules\Resto\Domain\Models\CateringPackage;
use Modules\Resto\Domain\Models\Outlet;

class CateringController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status');
        $outletId = $request->input('outlet_id');

        $orders = CateringOrder::with(['outlet', 'package', 'user'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->latest('id')
            ->paginate(15);

        $outlets = Outlet::where('is_active', true)->get();

        return view('resto::catering.index', [
            'orders' => $orders,
            'outlets' => $outlets,
            'selectedStatus' => $status,
            'selectedOutletId' => $outletId ? (int) $outletId : null,
        ]);
    }

    public function create(): View
    {
        $outlets = Outlet::where('is_active', true)->get();
        $packages = CateringPackage::where('is_active', true)->get();

        return view('resto::catering.create', [
            'outlets' => $outlets,
            'packages' => $packages,
        ]);
    }

    public function store(Request $request, CreateCateringOrderAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'delivery_address' => ['required', 'string'],
            'event_date' => ['required', 'date', 'after_or_equal:today'],
            'event_time' => ['nullable', 'string'],
            'pax' => ['required', 'integer', 'min:10', 'max:2000'],
            'package_id' => ['nullable', 'exists:resto_catering_packages,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $action->handle(
            outletId: (int) $validated['outlet_id'],
            customerName: $validated['customer_name'],
            customerPhone: $validated['customer_phone'],
            deliveryAddress: $validated['delivery_address'],
            eventDate: $validated['event_date'],
            pax: (int) $validated['pax'],
            packageId: $validated['package_id'] ? (int) $validated['package_id'] : null,
            user: $request->user(),
            eventTime: $validated['event_time'] ?? '11:00:00',
            notes: $validated['notes'] ?? null
        );

        return redirect()->route('resto.catering.show', $order)
            ->with('success', "Penawaran katering #{$order->number} berhasil dibuat. Silakan konfirmasi deposit 30%.");
    }

    public function show(CateringOrder $order): View
    {
        $order->load(['outlet', 'package', 'user', 'depositIntent']);

        return view('resto::catering.show', [
            'order' => $order,
        ]);
    }

    public function holdDeposit(Request $request, CateringOrder $order, HoldCateringDepositAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['nullable', 'string', 'size:6'],
        ]);

        $action->handle(
            cateringOrder: $order,
            customerUser: $request->user(),
            pin: $validated['pin'] ?? null
        );

        return back()->with('success', 'Deposit 30% berhasil ditahan di rekening bersama (escrow). Pesanan katering kini berstatus terkonfirmasi.');
    }

    public function complete(CateringOrder $order, DeliverAndCompleteCateringAction $action): RedirectResponse
    {
        $action->handle($order);

        return back()->with('success', 'Pesanan katering berhasil diselesaikan: Deposit dicairkan dan sisa 70% telah dipotong dari dompet pelanggan.');
    }

    public function cancel(Request $request, CateringOrder $order, CancelCateringAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $action->handle($order, $validated['reason']);

        return back()->with('success', 'Pesanan katering berhasil dibatalkan sesuai ketentuan kebijakan pembatalan (H-3).');
    }
}
