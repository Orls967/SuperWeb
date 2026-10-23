<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\C2m\VmiAndC2mService;
use Modules\Manufacturing\Domain\Models\C2m\C2mCustomOrder;
use Modules\Manufacturing\Domain\Models\C2m\VmiReplenishment;
use Throwable;

class VmiAndC2mController extends Controller
{
    public function __construct(
        private readonly VmiAndC2mService $service
    ) {}

    public function index(): View
    {
        return view('manufacturing::vmi_c2m', [
            'replenishments' => VmiReplenishment::orderByDesc('created_at')->limit(50)->get(),
            'customOrders' => C2mCustomOrder::with('bomNodes')->orderByDesc('created_at')->limit(50)->get(),
            'vmiSummary' => $this->service->getVmiDashboardSummary(),
            'c2mBoard' => $this->service->getC2mProductionBoard(),
        ]);
    }

    public function triggerVmi(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|min:1',
            'sku' => 'required|string|max:64',
            'current_stock' => 'required|integer|min:0',
            'reorder_point' => 'required|integer|min:1',
            'reorder_qty' => 'required|integer|min:1',
            'unit_cost_idr' => 'required|integer|min:1',
            'contract_plafond_idr' => 'nullable|integer|min:1',
        ]);

        $replenishment = $this->service->evaluateVmiTrigger(
            supplierId: (int) $data['supplier_id'],
            sku: $data['sku'],
            currentStock: (int) $data['current_stock'],
            reorderPoint: (int) $data['reorder_point'],
            reorderQty: (int) $data['reorder_qty'],
            unitCostIdr: (int) $data['unit_cost_idr'],
            contractPlafondIdr: isset($data['contract_plafond_idr']) ? (int) $data['contract_plafond_idr'] : 50_000_000
        );

        if ($replenishment === null) {
            return back()->with('info', "Stok {$data['sku']} masih di atas titik pesan ulang. Tidak perlu auto-PO.");
        }

        return back()->with('success', "VMI replenishment {$replenishment->vmi_code} berhasil diproses (Status: {$replenishment->status}).");
    }

    public function approveVmi(VmiReplenishment $replenishment): RedirectResponse
    {
        try {
            $approved = $this->service->approveVmiReplenishment($replenishment);

            return back()->with('success', "VMI {$approved->vmi_code} berhasil disetujui dan komitmen anggaran diposting.");
        } catch (Throwable $e) {
            return back()->withErrors(['vmi' => $e->getMessage()]);
        }
    }

    public function storeC2mOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_model' => 'required|string|max:64',
            'size_mm' => 'required|integer|min:1|max:3000',
            'material' => 'required|string|max:64',
            'finish' => 'required|string|max:64',
            'tolerance' => 'nullable|string|max:64',
            'raw_material_cost_idr' => 'required|integer|min:1000',
        ]);

        try {
            $parameters = [
                'size_mm' => (int) $data['size_mm'],
                'material' => $data['material'],
                'finish' => $data['finish'],
                'tolerance' => $data['tolerance'] ?? 'STANDARD',
            ];

            $bomItemsCost = [
                $data['material'] => (int) $data['raw_material_cost_idr'],
            ];

            $order = $this->service->configureAndQuoteC2m(
                userId: (int) $request->user()->id,
                baseModel: $data['base_model'],
                parameters: $parameters,
                bomItemsCost: $bomItemsCost
            );

            return back()->with('success', "C2M Order {$order->c2m_code} divalidasi. Harga: Rp ".number_format($order->final_price_idr));
        } catch (Throwable $e) {
            return back()->withErrors(['c2m' => $e->getMessage()]);
        }
    }

    public function issueSpk(C2mCustomOrder $order): RedirectResponse
    {
        try {
            $updated = $this->service->issueFactorySpk($order);

            return back()->with('success', "SPK Pabrik {$updated->spk_number} terbit untuk {$updated->c2m_code}.");
        } catch (Throwable $e) {
            return back()->withErrors(['c2m' => $e->getMessage()]);
        }
    }

    public function cancelC2m(C2mCustomOrder $order): RedirectResponse
    {
        try {
            $cancelled = $this->service->cancelC2mOrder($order);

            return back()->with('success', "Order {$cancelled->c2m_code} dibatalkan (Status: {$cancelled->status}).");
        } catch (Throwable $e) {
            return back()->withErrors(['c2m' => $e->getMessage()]);
        }
    }
}
