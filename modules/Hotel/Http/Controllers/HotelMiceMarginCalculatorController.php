<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Algorithms\EventMarginCalculator;

final class HotelMiceMarginCalculatorController extends Controller
{
    public function __construct(
        private readonly EventMarginCalculator $marginCalculator
    ) {}

    public function index(): View
    {
        return view('hotel::event_gross_margin_calculator_view');
    }

    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'proposal_id' => 'required|string|max:64',
            'revenue' => 'required|array',
            'costs' => 'required|array',
            'min_guardrail_margin' => 'nullable|numeric|min:0|max:100',
        ]);

        $certificate = $this->marginCalculator->calculate(
            proposalId: $validated['proposal_id'],
            revenueBreakdown: array_map('intval', $validated['revenue']),
            costBreakdown: array_map('intval', $validated['costs']),
            guardrailMinMarginPercent: isset($validated['min_guardrail_margin']) ? (float) $validated['min_guardrail_margin'] : 40.0
        );

        return response()->json([
            'success' => true,
            'total_revenue_idr' => $certificate->totalRevenueIdr,
            'total_direct_cost_idr' => $certificate->totalDirectCostIdr,
            'gross_margin_idr' => $certificate->grossMarginIdr,
            'gross_margin_percent' => $certificate->grossMarginPercent,
            'guardrail_threshold_percent' => $certificate->guardrailThresholdPercent,
            'is_compliant' => $certificate->isCompliant,
            'status' => $certificate->status,
            'price_shortfall_idr' => $certificate->priceShortfallIdr,
            'signature_hash' => $certificate->signatureHash,
        ]);
    }
}
