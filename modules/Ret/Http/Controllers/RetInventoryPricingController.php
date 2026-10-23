<?php

declare(strict_types=1);

namespace Modules\Ret\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Ret\Application\Actions\AuditShelfPlanogramComplianceAction;
use Modules\Ret\Application\Actions\ExecuteDynamicExpiryMarkdownPricingAction;
use Modules\Ret\Application\Actions\ReconcileStoreShrinkageAndInventoryAction;
use Modules\Ret\Domain\Models\RetInventoryItem;

class RetInventoryPricingController extends Controller
{
    public function index(): View
    {
        $items = RetInventoryItem::query()->latest()->take(20)->get();

        return view('ret::pricing.index', compact('items'));
    }

    public function markdown(Request $request, ExecuteDynamicExpiryMarkdownPricingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'days_remaining' => ['required', 'integer', 'min:0'],
        ]);

        $result = $action->execute($validated['sku'], (int) $validated['days_remaining']);

        return redirect()->back()->with('success', "Markdown Berhasil: SKU {$result['sku']} didiskon {$result['discount_pct']}% menjadi Rp ".number_format($result['marked_down_price'], 0, ',', '.'));
    }

    public function complianceView(): View
    {
        return view('ret::compliance.index');
    }

    public function auditPlanogram(Request $request, AuditShelfPlanogramComplianceAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'shelf_id' => ['required', 'string'],
            'expected_facings' => ['required', 'integer', 'min:1'],
            'detected_facings' => ['required', 'integer', 'min:0'],
            'out_of_stock_detected' => ['nullable', 'integer'],
        ]);

        $result = $action->execute($validated);

        return redirect()->back()->with('success', "Audit Rak {$result['shelf_id']}: Skor {$result['compliance_score_pct']}% ({$result['status']})");
    }

    public function reconcileShrinkage(Request $request, ReconcileStoreShrinkageAndInventoryAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string'],
            'physical_count' => ['required', 'integer', 'min:0'],
        ]);

        $result = $action->execute($validated['sku'], (int) $validated['physical_count']);

        return redirect()->back()->with('success', "Rekonsiliasi Selesai: SKU {$result['sku']} susut {$result['shrinkage_qty']} unit (Write-off: Rp ".number_format($result['write_off_minor'], 0, ',', '.').')');
    }
}
