<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Services\HotelCoBrandCardCashbackProcessor;
use Modules\Hotel\Domain\Services\HotelCoBrandCardReconciliationService;

final class HotelCoBrandCardController extends Controller
{
    public function __construct(
        protected HotelCoBrandCardCashbackProcessor $cashbackProcessor,
        protected HotelCoBrandCardReconciliationService $reconciliationService
    ) {}

    public function index(Request $request): View
    {
        $passes = HtlTravelPass::where('is_active', true)->limit(20)->get();

        return view('hotel::co_brand_card_management_center', [
            'passes' => $passes,
        ]);
    }

    public function processCashback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'travel_pass_id' => 'required|string',
            'bank_code' => 'required|string',
            'spend_amount_idr' => 'required|integer|min:10000',
            'card_tier' => 'required|string',
            'merchant_category' => 'nullable|string',
        ]);

        $result = $this->cashbackProcessor->processCardSpendCashback(
            travelPassId: $validated['travel_pass_id'],
            bankCode: $validated['bank_code'],
            spendAmountIdr: (int) $validated['spend_amount_idr'],
            cardTier: $validated['card_tier'],
            merchantCategory: $validated['merchant_category'] ?? 'HOTEL_DIRECT'
        );

        return redirect()->back()->with('success', "Cashback co-brand berhasil diproses! {$result['cashback_points']} PTS dikreditkan.");
    }
}
