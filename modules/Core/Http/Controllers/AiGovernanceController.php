<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Contracts\DynamicPricingEngineInterface;
use Modules\Core\Contracts\StockPrescriptionEngineInterface;
use Modules\Core\Contracts\TransactionAnomalyDetectorInterface;
use Modules\Core\Domain\Models\CoreAiAnomalyQuarantine;
use Modules\Core\Domain\Models\CoreAiDecisionSnapshot;
use Modules\Core\Domain\Models\CoreAiPricingRule;
use Modules\Core\Domain\Models\CoreAiStockRecommendation;

class AiGovernanceController extends Controller
{
    public function index(): View
    {
        $kpi = [
            'total_decisions' => CoreAiDecisionSnapshot::count(),
            'active_quarantines' => CoreAiAnomalyQuarantine::where('status', 'QUARANTINED')->count(),
            'pricing_rules' => CoreAiPricingRule::where('is_active', true)->count(),
            'stock_recommendations' => CoreAiStockRecommendation::count(),
        ];

        $pricingRules = CoreAiPricingRule::orderBy('created_at', 'desc')->take(10)->get();
        $quarantines = CoreAiAnomalyQuarantine::orderBy('created_at', 'desc')->take(10)->get();
        $stockRecommendations = CoreAiStockRecommendation::orderBy('created_at', 'desc')->take(10)->get();
        $decisionSnapshots = CoreAiDecisionSnapshot::orderBy('created_at', 'desc')->take(10)->get();

        return view('core::ai.index', [
            'kpi' => $kpi,
            'pricingRules' => $pricingRules,
            'quarantines' => $quarantines,
            'stockRecommendations' => $stockRecommendations,
            'decisionSnapshots' => $decisionSnapshots,
        ]);
    }

    public function calculatePricing(
        Request $request,
        DynamicPricingEngineInterface $engine
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:64'],
            'base_price_idr' => ['required', 'integer', 'min:100'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'hours_to_expiry' => ['nullable', 'integer', 'min:0'],
            'space_occupancy_rate' => ['nullable', 'numeric', 'between:0,1'],
            'recent_sales_velocity' => ['nullable', 'integer', 'min:0'],
            'baseline_sales_velocity' => ['nullable', 'integer', 'min:1'],
            'seed' => ['nullable', 'integer'],
        ]);

        $result = $engine->calculatePrice(
            itemCode: (string) $validated['item_code'],
            basePriceIdr: (int) $validated['base_price_idr'],
            currentStock: (int) $validated['current_stock'],
            hoursToExpiry: isset($validated['hours_to_expiry']) ? (int) $validated['hours_to_expiry'] : null,
            spaceOccupancyRate: isset($validated['space_occupancy_rate']) ? (float) $validated['space_occupancy_rate'] : 0.50,
            recentSalesVelocity: isset($validated['recent_sales_velocity']) ? (int) $validated['recent_sales_velocity'] : 10,
            baselineSalesVelocity: isset($validated['baseline_sales_velocity']) ? (int) $validated['baseline_sales_velocity'] : 10,
            seed: isset($validated['seed']) ? (int) $validated['seed'] : 42,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
            ]);
        }

        return redirect()->route('admin.ai.index')
            ->with('success', 'Harga dinamis berhasil dihitung: Rp '.number_format($result->finalPriceIdr)." (Multiplier: {$result->multiplier}x)");
    }

    public function screenAnomaly(
        Request $request,
        TransactionAnomalyDetectorInterface $detector
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'transaction_ref' => ['required', 'string', 'max:64'],
            'customer_ref' => ['required', 'string', 'max:64'],
            'amount_idr' => ['required', 'integer', 'min:1'],
            'channel' => ['nullable', 'string', 'max:32'],
            'recent_tx_count_10m' => ['nullable', 'integer', 'min:0'],
            'avg_historical_amount_idr' => ['nullable', 'integer', 'min:0'],
            'std_dev_historical_amount_idr' => ['nullable', 'integer', 'min:0'],
            'is_late_night' => ['nullable', 'boolean'],
            'is_split_bill_pattern' => ['nullable', 'boolean'],
            'seed' => ['nullable', 'integer'],
        ]);

        $result = $detector->evaluateTransaction(
            transactionRef: (string) $validated['transaction_ref'],
            customerRef: (string) $validated['customer_ref'],
            amountIdr: (int) $validated['amount_idr'],
            channel: (string) ($validated['channel'] ?? 'ONLINE'),
            recentTxCount10m: isset($validated['recent_tx_count_10m']) ? (int) $validated['recent_tx_count_10m'] : 1,
            avgHistoricalAmountIdr: isset($validated['avg_historical_amount_idr']) ? (int) $validated['avg_historical_amount_idr'] : 0,
            stdDevHistoricalAmountIdr: isset($validated['std_dev_historical_amount_idr']) ? (int) $validated['std_dev_historical_amount_idr'] : 0,
            isLateNight: (bool) ($validated['is_late_night'] ?? false),
            isSplitBillPattern: (bool) ($validated['is_split_bill_pattern'] ?? false),
            seed: isset($validated['seed']) ? (int) $validated['seed'] : 42,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
            ]);
        }

        $message = $result->isQuarantined
            ? "PERINGATAN: Transaksi masuk karantina! Skor Risiko: {$result->riskScore}"
            : "Evaluasi transaksi normal: Skor Risiko: {$result->riskScore}";

        return redirect()->route('admin.ai.index')
            ->with($result->isQuarantined ? 'warning' : 'success', $message);
    }

    public function generateStockRecommendation(
        Request $request,
        StockPrescriptionEngineInterface $engine
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'item_code' => ['required', 'string', 'max:64'],
            'item_name' => ['required', 'string', 'max:255'],
            'current_stock' => ['required', 'integer', 'min:0'],
            'avg_daily_demand' => ['required', 'integer', 'min:1'],
            'lead_time_days' => ['required', 'integer', 'min:1'],
            'unit_cost_idr' => ['required', 'integer', 'min:100'],
            'season_code' => ['nullable', 'string', 'max:32'],
            'seed' => ['nullable', 'integer'],
        ]);

        $result = $engine->generateRecommendation(
            itemCode: (string) $validated['item_code'],
            itemName: (string) $validated['item_name'],
            currentStock: (int) $validated['current_stock'],
            avgDailyDemand: (int) $validated['avg_daily_demand'],
            leadTimeDays: (int) $validated['lead_time_days'],
            unitCostIdr: (int) $validated['unit_cost_idr'],
            seasonCode: (string) ($validated['season_code'] ?? 'REGULAR'),
            seed: isset($validated['seed']) ? (int) $validated['seed'] : 42,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => $result->toArray(),
            ]);
        }

        return redirect()->route('admin.ai.index')
            ->with('success', "Rekomendasi stok diterbitkan: ROP {$result->calculatedRop} unit, Usulan Order: {$result->suggestedOrderQty} unit");
    }

    public function releaseQuarantine(
        Request $request,
        CoreAiAnomalyQuarantine $quarantine
    ): JsonResponse|RedirectResponse {
        $validated = $request->validate([
            'release_justification' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $quarantine->update([
            'status' => 'RELEASED',
            'released_at' => now(),
            'released_by' => $request->user()?->name ?? 'Admin Supervisor',
            'release_justification' => (string) $validated['release_justification'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Karantina berhasil dirilis dengan otorisasi supervisor.',
                'data' => $quarantine,
            ]);
        }

        return redirect()->route('admin.ai.index')
            ->with('success', "Karantina transaksi {$quarantine->transaction_ref} berhasil dirilis.");
    }
}
