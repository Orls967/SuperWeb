<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\C2m\VmiAndC2mService;
use Modules\Procurement\Domain\Models\VmiAgreement;

class VmiPortalController extends Controller
{
    public function __construct(
        private readonly VmiAndC2mService $service
    ) {}

    /**
     * D82-1 Portal VMI untuk pemasok (role: supplier) — Anti-IDOR: hanya melihat SKU miliknya.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $isInternal = (method_exists($user, 'hasAnyRbacRole') && $user->hasAnyRbacRole(['admin', 'procurement', 'procurement_manager']))
            || in_array($user->role, ['admin', 'procurement', 'procurement_manager'], true);

        $agreementsQuery = VmiAgreement::query();

        if (! $isInternal) {
            $agreementsQuery->where('supplier_id', $user->id);
        }

        $agreements = $agreementsQuery->orderBy('sku')->get();
        $replenishments = $this->service->getReplenishmentsForSupplier($isInternal ? null : (int) $user->id);

        return view('procurement::vmi_portal', [
            'agreements' => $agreements,
            'replenishments' => $replenishments,
            'supplier' => $user,
        ]);
    }

    /**
     * D82-1 Trigger kalkulasi dan pembuatan auto-PO VMI.
     */
    public function trigger(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer|min:1',
            'sku' => 'required|string|max:64',
            'mean_daily_demand' => 'nullable|integer|min:1',
            'demand_std_dev' => 'nullable|integer|min:0',
            'lead_time_days' => 'nullable|integer|min:1',
            'service_level_bps' => 'nullable|integer|min:1',
            'review_period_days' => 'nullable|integer|min:1',
            'on_hand' => 'nullable|integer|min:0',
            'on_order' => 'nullable|integer|min:0',
            'backorder' => 'nullable|integer|min:0',
            'pack_size' => 'nullable|integer|min:1',
            'unit_cost_idr' => 'nullable|integer|min:1',
            'current_stock' => 'nullable|integer|min:0',
            'reorder_point' => 'nullable|integer|min:1',
            'reorder_qty' => 'nullable|integer|min:1',
            'contract_plafond_idr' => 'nullable|integer|min:1',
        ]);

        $user = $request->user();
        $isInternal = (method_exists($user, 'hasAnyRbacRole') && $user->hasAnyRbacRole(['admin', 'procurement', 'procurement_manager']))
            || in_array($user->role, ['admin', 'procurement', 'procurement_manager'], true);

        // Anti-IDOR: Pemasok hanya boleh mentrigger SKU yang ada dalam kontrak aktif miliknya
        $hasContract = VmiAgreement::where('supplier_id', $user->id)
            ->where('sku', $data['sku'])
            ->where('status', 'ACTIVE')
            ->exists();

        if (! $isInternal && ! $hasContract) {
            abort(403, 'Akses ditolak: Pemasok tidak memiliki kontrak aktif untuk SKU ini.');
        }

        $agreement = VmiAgreement::where('sku', $data['sku'])->first();
        $unitCost = $data['unit_cost_idr'] ?? $agreement?->unit_cost_idr ?? 12_000;
        $contractPlafond = $data['contract_plafond_idr'] ?? $agreement?->contract_plafond_idr ?? 50_000_000;

        $replenishment = $this->service->evaluateVmiTrigger(
            supplierId: (int) $data['supplier_id'],
            sku: $data['sku'],
            currentStock: (int) ($data['on_hand'] ?? $data['current_stock'] ?? 0),
            reorderPoint: (int) ($data['reorder_point'] ?? $agreement?->reorder_point ?? 500),
            reorderQty: (int) ($data['reorder_qty'] ?? $agreement?->reorder_qty ?? 100),
            unitCostIdr: $unitCost,
            contractPlafondIdr: $contractPlafond,
            meanDailyDemand: isset($data['mean_daily_demand']) ? (int) $data['mean_daily_demand'] : null,
            demandStdDev: isset($data['demand_std_dev']) ? (int) $data['demand_std_dev'] : null,
            leadTimeDays: isset($data['lead_time_days']) ? (int) $data['lead_time_days'] : null,
            serviceLevelBps: isset($data['service_level_bps']) ? (int) $data['service_level_bps'] : null,
            reviewPeriodDays: isset($data['review_period_days']) ? (int) $data['review_period_days'] : null,
            packSize: isset($data['pack_size']) ? (int) $data['pack_size'] : null,
            onHand: isset($data['on_hand']) ? (int) $data['on_hand'] : null,
            onOrder: isset($data['on_order']) ? (int) $data['on_order'] : null,
            backorder: isset($data['backorder']) ? (int) $data['backorder'] : null,
        );

        if ($replenishment === null) {
            return back()->with('info', "Stok SKU {$data['sku']} masih aman di atas titik pesan ulang.");
        }

        return back()->with('success', "VMI Replenishment {$replenishment->vmi_code} berhasil diproses (Status: {$replenishment->status}).");
    }

    /**
     * D82-1 Persetujuan usulan replenishment di atas plafon kontrak oleh procurement_manager.
     */
    public function approve(Request $request, int|string $replenishment): RedirectResponse
    {
        $user = $request->user();
        $isAuthorized = (method_exists($user, 'hasAnyRbacRole') && $user->hasAnyRbacRole(['procurement_manager', 'procurement', 'admin']))
            || in_array($user->role, ['procurement_manager', 'procurement', 'admin'], true);

        if (! $isAuthorized) {
            abort(403, 'Akses ditolak: Hanya manajer pengadaan yang dapat menyetujui VMI replenishment di atas plafon.');
        }

        $rep = $this->service->approveVmiReplenishmentById((int) $replenishment);

        return back()->with('success', "VMI {$rep->vmi_code} berhasil disetujui.");
    }
}
