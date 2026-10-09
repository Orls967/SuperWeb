<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Actions\AddOrderItemAction;
use Modules\Resto\Application\Actions\CalculateHidangBillAction;
use Modules\Resto\Application\Actions\CloseShiftAction;
use Modules\Resto\Application\Actions\OpenShiftAction;
use Modules\Resto\Application\Actions\OpenTableSessionAction;
use Modules\Resto\Application\Actions\PayOrderAction;
use Modules\Resto\Application\Actions\PresentHidangAction;
use Modules\Resto\Application\Actions\SettleCashAction;
use Modules\Resto\Application\Actions\ValidateOrderParkingAction;
use Modules\Resto\Application\Actions\VoidOrderAction;
use Modules\Resto\Domain\Enums\OrderStatus;
use Modules\Resto\Domain\Enums\ShiftStatus;
use Modules\Resto\Domain\Enums\TrayStatus;
use Modules\Resto\Domain\Models\DisplayTray;
use Modules\Resto\Domain\Models\MenuCategory;
use Modules\Resto\Domain\Models\Order;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\RestoStaffAssignment;
use Modules\Resto\Domain\Models\RestoTable;
use Modules\Resto\Domain\Models\Shift;
use Modules\Resto\Domain\Models\TableSession;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        // Determine active outlet for this cashier / user
        $assignment = RestoStaffAssignment::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        $selectedOutletId = $request->input('outlet_id')
            ?? ($assignment?->outlet_id)
            ?? Outlet::where('type', 'outlet')->where('is_active', true)->first()?->id;

        $outlet = Outlet::findOrFail($selectedOutletId);
        $allOutlets = Outlet::where('type', 'outlet')->where('is_active', true)->get();

        // Active shift for this cashier
        $activeShift = Shift::where('outlet_id', $outlet->id)
            ->where('cashier_id', $user->id)
            ->where('status', ShiftStatus::OPEN)
            ->first();

        // Tables with their active open session
        $tables = RestoTable::where('outlet_id', $outlet->id)
            ->with(['activeSession.orders' => function ($q) {
                $q->whereIn('status', [OrderStatus::OPEN, OrderStatus::AWAITING_PAYMENT])->with('items.menuItem');
            }])
            ->get();

        // Display trays currently on display or in service
        $displayTrays = DisplayTray::where('outlet_id', $outlet->id)
            ->whereIn('status', [TrayStatus::ON_DISPLAY, TrayStatus::IN_SERVICE, TrayStatus::RETURNED])
            ->where('portions_remaining', '>', 0)
            ->with('menuItem')
            ->orderBy('expires_at')
            ->get();

        // Categories and menu items
        $categories = MenuCategory::with(['items' => function ($q) {
            $q->where('is_active', true)->orderBy('sort');
        }])->orderBy('sort')->get();

        return view('resto::pos.index', [
            'outlet' => $outlet,
            'allOutlets' => $allOutlets,
            'activeShift' => $activeShift,
            'tables' => $tables,
            'displayTrays' => $displayTrays,
            'categories' => $categories,
        ]);
    }

    public function openShift(Request $request, OpenShiftAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'opening_float' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle(
            cashierId: (int) $request->user()->id,
            outletId: (int) $validated['outlet_id'],
            openingFloat: (int) $validated['opening_float'],
            note: $validated['note'] ?? null
        );

        return redirect()->route('resto.pos.index', ['outlet_id' => $validated['outlet_id']])
            ->with('success', 'Shift kasir berhasil dibuka.');
    }

    public function closeShift(Request $request, CloseShiftAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'shift_id' => ['required', 'exists:resto_shifts,id'],
            'counted_cash' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);
        $closedShift = $action->handle(
            shift: $shift,
            countedCash: (int) $validated['counted_cash'],
            note: $validated['note'] ?? null
        );

        $varianceMsg = $closedShift->variance === 0
            ? 'Kas seimbang (tidak ada selisih).'
            : ($closedShift->variance > 0
                ? 'Ditemukan selisih lebih: Rp '.number_format($closedShift->variance, 0, ',', '.')
                : 'Ditemukan selisih kurang: Rp '.number_format(abs($closedShift->variance), 0, ',', '.'));

        return redirect()->route('resto.pos.index', ['outlet_id' => $closedShift->outlet_id])
            ->with('success', "Shift kasir #{$closedShift->id} berhasil ditutup. {$varianceMsg}");
    }

    public function settleCash(Request $request, SettleCashAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $action->handle(
            outletId: (int) $validated['outlet_id'],
            amount: (int) $validated['amount'],
            userId: (int) $request->user()->id,
            note: $validated['note'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return back()->with('success', 'Setoran kas laci ke rekening bank berhasil dicatat.');
    }

    public function openSession(Request $request, OpenTableSessionAction $action): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'exists:resto_tables,id'],
            'guest_count' => ['required', 'integer', 'min:1'],
            'customer_id' => ['nullable', 'exists:users,id'],
            'guest_name' => ['nullable', 'string', 'max:100'],
        ]);

        $session = $action->handle(
            tableId: (int) $validated['table_id'],
            guestCount: (int) $validated['guest_count'],
            openedBy: (int) $request->user()->id,
            customerId: isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            guestName: $validated['guest_name'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => "Sesi meja {$session->table->code} berhasil dibuka.",
            'session' => $session,
        ]);
    }

    public function getSession(TableSession $session): JsonResponse
    {
        $session->load([
            'table',
            'orders' => function ($q) {
                $q->whereIn('status', [OrderStatus::OPEN, OrderStatus::AWAITING_PAYMENT])
                    ->with(['items.menuItem', 'items.tray.menuItem']);
            },
        ]);

        return response()->json([
            'success' => true,
            'session' => $session,
        ]);
    }

    public function presentHidang(Request $request, TableSession $session, PresentHidangAction $action): JsonResponse
    {
        $validated = $request->validate([
            'trays' => ['required', 'array'],
        ]);

        $order = $action->handle($session, $validated['trays']);

        return response()->json([
            'success' => true,
            'message' => 'Piring hidang berhasil dibawa ke meja.',
            'order' => $order,
        ]);
    }

    public function addItem(Request $request, TableSession $session, AddOrderItemAction $action): JsonResponse
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'exists:resto_menu_items,id'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $order = Order::where('table_session_id', $session->id)
            ->whereIn('status', [OrderStatus::OPEN, OrderStatus::AWAITING_PAYMENT])
            ->firstOrFail();

        $item = $action->handle(
            order: $order,
            menuItemId: (int) $validated['menu_item_id'],
            qty: (int) ($validated['qty'] ?? 1)
        );

        return response()->json([
            'success' => true,
            'message' => "Item {$item->name_snapshot} berhasil ditambahkan.",
            'item' => $item,
        ]);
    }

    public function calculateBill(Request $request, TableSession $session, CalculateHidangBillAction $action): JsonResponse
    {
        $validated = $request->validate([
            'consumed_statuses' => ['nullable', 'array'],
            'discount' => ['nullable', 'integer', 'min:0'],
        ]);

        $order = $action->handle(
            session: $session,
            consumedStatuses: $validated['consumed_statuses'] ?? [],
            discount: (int) ($validated['discount'] ?? 0),
            userId: (int) $request->user()->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Tagihan meja berhasil dihitung.',
            'order' => $order,
        ]);
    }

    public function payOrder(Request $request, Order $order, PayOrderAction $action): JsonResponse
    {
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash,wallet,voucher,points,split'],
            'cash_tendered' => ['nullable', 'integer', 'min:0'],
            'pin' => ['nullable', 'string', 'max:6'],
            'idempotency_key' => ['nullable', 'string'],
            'parking_ticket_number' => ['nullable', 'string'],
            'mall_voucher_code' => ['nullable', 'string'],
            'redeem_points' => ['nullable', 'integer', 'min:0'],
        ]);

        $activeShift = Shift::where('cashier_id', $request->user()->id)
            ->where('outlet_id', $order->outlet_id)
            ->where('status', ShiftStatus::OPEN)
            ->first();

        $paidOrder = $action->handle(
            order: $order,
            paymentMethod: $validated['payment_method'],
            cashTendered: isset($validated['cash_tendered']) ? (int) $validated['cash_tendered'] : null,
            pin: $validated['pin'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
            shiftId: $activeShift?->id,
            cashierUserId: (int) $request->user()->id,
            parkingTicketNumber: $validated['parking_ticket_number'] ?? null,
            mallVoucherCode: $validated['mall_voucher_code'] ?? null,
            redeemPoints: isset($validated['redeem_points']) ? (int) $validated['redeem_points'] : null
        );

        return response()->json([
            'success' => true,
            'message' => "Pembayaran pesanan {$paidOrder->number} berhasil diselesaikan.",
            'order' => $paidOrder,
            'receipt_url' => route('resto.pos.order.receipt', $paidOrder),
        ]);
    }

    public function validateParking(Request $request, Order $order, ValidateOrderParkingAction $action): JsonResponse
    {
        $validated = $request->validate([
            'ticket_number' => ['required', 'string'],
        ]);

        $result = $action->handle($order, $validated['ticket_number']);

        return response()->json([
            'success' => true,
            'message' => $result->receiptLine(),
            'result' => $result,
        ]);
    }

    public function voidOrder(Request $request, Order $order, VoidOrderAction $action): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $voidedOrder = $action->handle(
            order: $order,
            actor: $request->user(),
            reason: $validated['reason']
        );

        return response()->json([
            'success' => true,
            'message' => "Pesanan {$voidedOrder->number} berhasil dibatalkan (VOID).",
            'order' => $voidedOrder,
        ]);
    }

    public function receipt(Order $order): View
    {
        $order->load(['outlet', 'customer', 'shift.cashier', 'items.menuItem']);

        // Generate digital receipt QR code (SVG)
        $renderer = new ImageRenderer(
            new RendererStyle(120),
            new SvgImageBackEnd
        );
        $writer = new Writer($renderer);
        $qrSvg = $writer->writeString(url()->current());

        return view('resto::pos.receipt', [
            'order' => $order,
            'qrSvg' => $qrSvg,
        ]);
    }
}
