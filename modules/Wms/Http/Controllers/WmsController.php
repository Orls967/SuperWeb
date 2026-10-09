<?php

declare(strict_types=1);

namespace Modules\Wms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Wms\Application\Services\WmsService;
use Modules\Wms\Domain\Models\Bin;
use Modules\Wms\Domain\Models\BinStock;
use Modules\Wms\Domain\Models\CycleCount;
use Modules\Wms\Domain\Models\DockAppointment;
use Modules\Wms\Domain\Models\Replenishment;
use Modules\Wms\Domain\Models\Slotting;
use Modules\Wms\Domain\Models\Task;
use Modules\Wms\Domain\Models\Transfer;
use Modules\Wms\Domain\Models\Warehouse;
use Modules\Wms\Domain\Models\Wave;

class WmsController extends Controller
{
    public function __construct(private readonly WmsService $service) {}

    public function index(): View
    {
        return view('wms::index', [
            'warehouses' => Warehouse::withCount('zones')->orderBy('code')->get(),
            'stocks' => BinStock::orderBy('product_id')->limit(100)->get(),
            'tasks' => Task::whereIn('status', ['open'])->orderBy('priority')->limit(50)->get(),
            'transfers' => Transfer::orderByDesc('created_at')->limit(30)->get(),
            'counts' => CycleCount::orderByDesc('created_at')->limit(20)->get(),
            'appointments' => DockAppointment::orderBy('window_start')->limit(20)->get(),
            'replenishments' => Replenishment::with('bin')->limit(30)->get(),
            'slottings' => Slotting::orderBy('abc_class')->limit(50)->get(),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40', 'name' => 'required|string|max:160',
            'kind' => 'required|in:dc,raw,fg,quarantine,transit,consignment,reefer',
            'address' => 'nullable|string|max:255', 'city' => 'nullable|string|max:60',
        ]);
        $this->service->createWarehouse($data);

        return back()->with('success', 'Gudang dibuat.');
    }

    public function storePutaway(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bin_id' => 'required|integer|exists:wms_bins,id',
            'product_id' => 'required|integer|exists:store_products,id',
            'qty' => 'required|numeric|gt:0', 'lot_number' => 'nullable|string|max:60',
            'status' => 'required|in:available,quarantine,blocked',
        ]);
        $this->service->putaway(
            Bin::findOrFail($data['bin_id']), (int) $data['product_id'], (float) $data['qty'],
            $request->user(), $data['lot_number'] ?? null, $data['status']
        );

        return back()->with('success', 'Putaway tercatat.');
    }

    public function storePick(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bin_id' => 'required|integer|exists:wms_bins,id',
            'product_id' => 'required|integer|exists:store_products,id',
            'qty' => 'required|numeric|gt:0', 'lot_number' => 'nullable|string|max:60',
            'method' => 'required|in:fifo,fefo',
        ]);

        try {
            $this->service->pick(
                Bin::findOrFail($data['bin_id']), (int) $data['product_id'], (float) $data['qty'],
                $request->user(), $data['lot_number'] ?? null, $data['method']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['qty' => $e->getMessage()]);
        }

        return back()->with('success', 'Pick tercatat.');
    }

    public function storeWave(Request $request): RedirectResponse
    {
        $data = $request->validate(['strategy' => 'required|in:fifo,fefo,zone,batch']);
        $this->service->createWave($data['strategy']);

        return back()->with('success', 'Wave dibuat.');
    }

    public function releaseWave(Wave $wave): RedirectResponse
    {
        try {
            $this->service->releaseWave($wave);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['wave' => $e->getMessage()]);
        }

        return back()->with('success', "Wave {$wave->code} dirilis.");
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => 'required|uuid|exists:wms_warehouses,id',
            'to_warehouse_id' => 'required|uuid|exists:wms_warehouses,id',
            'product_id' => 'required|integer|exists:store_products,id',
            'qty' => 'required|numeric|gt:0', 'lot_number' => 'nullable|string|max:60',
            'cross_dock' => 'sometimes|boolean',
        ]);
        $transfer = $this->service->createTransfer(
            Warehouse::findOrFail($data['from_warehouse_id']),
            Warehouse::findOrFail($data['to_warehouse_id']),
            [['product_id' => $data['product_id'], 'qty' => $data['qty'], 'lot_number' => $data['lot_number'] ?? null]],
            $request->user(),
            (bool) ($data['cross_dock'] ?? false),
        );

        return back()->with('success', "Transfer {$transfer->number} dibuat.");
    }

    public function shipTransfer(Transfer $transfer, Request $request): RedirectResponse
    {
        try {
            $this->service->shipTransfer($transfer, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('success', "Transfer {$transfer->number} dalam perjalanan.");
    }

    public function receiveTransfer(Transfer $transfer, Request $request): RedirectResponse
    {
        $data = $request->validate(['bin_id' => 'required|integer|exists:wms_bins,id']);

        try {
            $this->service->receiveTransfer($transfer, Bin::findOrFail($data['bin_id']), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['transfer' => $e->getMessage()]);
        }

        return back()->with('success', "Transfer {$transfer->number} diterima.");
    }

    public function storeCycleCount(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|uuid|exists:wms_warehouses,id',
            'bin_id' => 'required|integer|exists:wms_bins,id',
            'product_id' => 'required|integer|exists:store_products,id',
            'counted_qty' => 'required|numeric|min:0',
        ]);
        $this->service->startCycleCount(
            Warehouse::findOrFail($data['warehouse_id']),
            [['bin_id' => $data['bin_id'], 'product_id' => $data['product_id'], 'counted_qty' => $data['counted_qty']]],
            $request->user()
        );

        return back()->with('success', 'Cycle count dibuat.');
    }

    public function submitCount(CycleCount $count, Request $request): RedirectResponse
    {
        try {
            $this->service->submitAdjustment($count, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }

        return back()->with('success', 'Penyesuaian diajukan (four-eyes).');
    }

    public function applyCount(CycleCount $count, Request $request): RedirectResponse
    {
        try {
            $this->service->approveAndApply($count, $request->user());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['count' => $e->getMessage()]);
        }

        return back()->with('success', "Cycle count {$count->number} diterapkan.");
    }

    public function storeDock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => 'required|uuid|exists:wms_warehouses,id',
            'direction' => 'required|in:in,out', 'reference' => 'required|string|max:80',
            'window_start' => 'required|date', 'window_end' => 'required|date|after:window_start',
            'carrier' => 'nullable|string|max:120',
        ]);

        try {
            $this->service->scheduleDock(
                Warehouse::findOrFail($data['warehouse_id']), $data['direction'], $data['reference'],
                $data['window_start'], $data['window_end'], $data['carrier'] ?? null
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['dock' => $e->getMessage()]);
        }

        return back()->with('success', 'Appointment dok dijadwalkan.');
    }

    public function computeReplenishment(): RedirectResponse
    {
        $results = $this->service->computeReplenishment();

        return back()->with('success', count($results).' pick-face diperiksa; tugas replenish dibuat bila di bawah minimum.');
    }
}
