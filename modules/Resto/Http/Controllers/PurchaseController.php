<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Actions\CreatePurchaseOrderAction;
use Modules\Resto\Application\Actions\PaySupplierAction;
use Modules\Resto\Application\Actions\ReceiveGoodsAction;
use Modules\Resto\Application\Queries\PayableAgingQuery;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\PurchaseOrder;
use Modules\Resto\Domain\Models\Supplier;

class PurchaseController extends Controller
{
    public function index(Request $request, PayableAgingQuery $agingQuery): View
    {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;

        $pos = PurchaseOrder::with(['outlet', 'supplier', 'creator'])
            ->when($outletId, fn ($q) => $q->where('outlet_id', $outletId))
            ->latest('id')
            ->paginate(15);

        $suppliers = Supplier::where('is_active', true)->get();
        $outlets = Outlet::where('is_active', true)->get();
        $ingredients = Ingredient::orderBy('name')->get();
        $aging = $agingQuery->get($outletId);

        return view('resto::purchases.index', [
            'pos' => $pos,
            'suppliers' => $suppliers,
            'outlets' => $outlets,
            'ingredients' => $ingredients,
            'aging' => $aging,
            'selectedOutletId' => $outletId,
        ]);
    }

    public function store(Request $request, CreatePurchaseOrderAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outlet_id' => ['required', 'exists:resto_outlets,id'],
            'supplier_id' => ['required', 'exists:resto_suppliers,id'],
            'expected_at' => ['nullable', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'exists:resto_ingredients,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.000001'],
            'lines.*.unit' => ['nullable', 'string'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],
        ]);

        $po = $action->handle(
            outletId: (int) $validated['outlet_id'],
            supplierId: (int) $validated['supplier_id'],
            linesData: $validated['lines'],
            creator: $request->user(),
            expectedAt: $validated['expected_at'] ?? null,
            autoSend: true
        );

        return redirect()->route('resto.purchases.show', $po)
            ->with('success', "Pesanan Pembelian #{$po->number} berhasil dibuat dan dikirim ke supplier.");
    }

    public function show(PurchaseOrder $purchase): View
    {
        $purchase->load(['outlet', 'supplier', 'creator', 'lines.ingredient', 'receipts.lines.poLine.ingredient', 'receipts.receiver']);

        return view('resto::purchases.show', [
            'po' => $purchase,
        ]);
    }

    public function receive(Request $request, PurchaseOrder $purchase, ReceiveGoodsAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'quality' => ['required', 'string', 'in:good,partial_reject'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.po_line_id' => ['required', 'exists:resto_purchase_order_lines,id'],
            'lines.*.qty_received_base_unit' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $receipt = $action->handle(
            po: $purchase,
            linesData: $validated['lines'],
            receiver: $request->user(),
            quality: $validated['quality'],
            note: $validated['note'] ?? null
        );

        return back()->with('success', "Penerimaan barang #{$receipt->id} berhasil dicatat. Stok bahan dan utang supplier diperbarui.");
    }

    public function pay(Request $request, PurchaseOrder $purchase, PaySupplierAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $action->handle(
            supplier: $purchase->supplier,
            amount: (int) $validated['amount'],
            po: $purchase,
            payerUser: $request->user(),
            note: $validated['note'] ?? null
        );

        return back()->with('success', 'Pembayaran utang dagang supplier berhasil diposting ke ledger.');
    }
}
