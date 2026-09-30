<?php

declare(strict_types=1);

namespace Modules\Resto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Resto\Application\Actions\ReceiveStockTransferAction;
use Modules\Resto\Application\Actions\ShipStockTransferAction;
use Modules\Resto\Domain\Models\Ingredient;
use Modules\Resto\Domain\Models\Outlet;
use Modules\Resto\Domain\Models\StockTransfer;

class StockTransferController extends Controller
{
    public function index(Request $request): View
    {
        $outletId = $request->input('outlet_id') ? (int) $request->input('outlet_id') : null;

        $transfers = StockTransfer::with(['fromOutlet', 'toOutlet', 'shipper', 'receiver'])
            ->when($outletId, function ($q) use ($outletId) {
                $q->where('from_outlet_id', $outletId)->orWhere('to_outlet_id', $outletId);
            })
            ->latest('id')
            ->paginate(15);

        $outlets = Outlet::where('is_active', true)->get();
        $ingredients = Ingredient::orderBy('name')->get();

        return view('resto::transfers.index', [
            'transfers' => $transfers,
            'outlets' => $outlets,
            'ingredients' => $ingredients,
            'selectedOutletId' => $outletId,
        ]);
    }

    public function store(Request $request, ShipStockTransferAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'from_outlet_id' => ['required', 'exists:resto_outlets,id'],
            'to_outlet_id' => ['required', 'exists:resto_outlets,id', 'different:from_outlet_id'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.ingredient_id' => ['required', 'exists:resto_ingredients,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.000001'],
        ]);

        $transfer = $action->handle(
            fromOutletId: (int) $validated['from_outlet_id'],
            toOutletId: (int) $validated['to_outlet_id'],
            lines: $validated['lines'],
            shipper: $request->user(),
            note: $validated['note'] ?? null
        );

        return redirect()->route('resto.transfers.show', $transfer)
            ->with('success', "Pengiriman transfer #{$transfer->number} berhasil dikirim (Status: In Transit).");
    }

    public function show(StockTransfer $transfer): View
    {
        $transfer->load(['fromOutlet', 'toOutlet', 'shipper', 'receiver']);

        return view('resto::transfers.show', [
            'transfer' => $transfer,
        ]);
    }

    public function receive(Request $request, StockTransfer $transfer, ReceiveStockTransferAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'variance_note' => ['nullable', 'string', 'max:255'],
            'received_qtys' => ['required', 'array'],
            'received_qtys.*' => ['required', 'numeric', 'min:0'],
        ]);

        $received = $action->handle(
            transfer: $transfer,
            receivedQtys: $validated['received_qtys'],
            receiver: $request->user(),
            varianceNote: $validated['variance_note'] ?? null
        );

        $msg = $received->status->value === 'discrepancy'
            ? 'Transfer diterima dengan selisih penerimaan (selisih diposting ke beban waste).'
            : 'Transfer berhasil diterima lengkap dan stok tujuan telah ditambahkan.';

        return back()->with('success', $msg);
    }
}
