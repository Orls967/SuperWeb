<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Ai;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Modules\Core\Application\Services\Ai\StockPrescriptionEngineService;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;

class AiStockRecommendationController extends Controller
{
    public function index(): View
    {
        $recommendations = CoreAiStockRecommendation::orderBy('created_at', 'desc')->paginate(20);

        return view('core::ai.stock-recommendations', [
            'recommendations' => $recommendations,
        ]);
    }

    public function store(Request $request, StockPrescriptionEngineService $engine): RedirectResponse
    {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:64'],
            'item_name' => ['required', 'string', 'max:255'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'avg_daily_demand' => ['required', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:1'],
            'unit_cost_idr' => ['required', 'integer', 'min:100'],
            'season_code' => ['nullable', 'string', 'max:32'],
            'historical_demands' => ['nullable', 'array'],
        ]);

        $res = $engine->generateRecommendation(
            itemCode: (string) $validated['item_code'],
            itemName: (string) $validated['item_name'],
            currentStock: (int) $validated['current_stock'],
            avgDailyDemand: (int) $validated['avg_daily_demand'],
            leadTimeDays: (int) $validated['lead_time_days'],
            unitCostIdr: (int) $validated['unit_cost_idr'],
            seasonCode: (string) ($validated['season_code'] ?? 'REGULAR'),
            historicalDemands: (array) ($validated['historical_demands'] ?? []),
        );

        return redirect()->route('core.ai.stock-recommendations.index')
            ->with('success', "Rekomendasi stok diterbitkan: Usulan PO {$res->suggestedOrderQty} unit.");
    }

    public function approve(Request $request, CoreAiStockRecommendation $recommendation): RedirectResponse
    {
        $user = $request->user();
        if ($user === null || ! in_array($user->role, ['planner_manager', 'admin'], true)) {
            abort(403, 'Akses ditolak: Hanya Planner Manager yang berwenang menyetujui rekomendasi pengadaan (D64-3).');
        }

        if ($recommendation->status === 'APPROVED_AS_PO') {
            return redirect()->route('core.ai.stock-recommendations.index')
                ->with('info', "Rekomendasi {$recommendation->recommendation_code} sudah disetujui sebelumnya.");
        }

        $poRef = 'PO-AUTO-'.Str::upper(Str::random(8));
        $recommendation->update([
            'status' => 'APPROVED_AS_PO',
            'approved_po_ref' => $poRef,
        ]);

        return redirect()->route('core.ai.stock-recommendations.index')
            ->with('success', "Rekomendasi disetujui. PO {$poRef} diterbitkan ke pengadaan.");
    }
}
