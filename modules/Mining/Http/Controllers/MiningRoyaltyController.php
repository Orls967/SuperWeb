<?php

declare(strict_types=1);

namespace Modules\Mining\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Mining\Application\Actions\AdjustCoalCommercialPriceAction;
use Modules\Mining\Domain\Models\MiningReferencePrice;
use Modules\Mining\Domain\Models\MiningRoyaltyRateVersion;
use Modules\Mining\Domain\Models\MiningRoyaltyReturn;

class MiningRoyaltyController extends Controller
{
    public function index()
    {
        $returns = MiningRoyaltyReturn::latest()->take(10)->get();
        $rateVersions = MiningRoyaltyRateVersion::all();
        $refPrices = MiningReferencePrice::latest()->take(10)->get();

        return view('mining::royalty', [
            'returns' => $returns,
            'rateVersions' => $rateVersions,
            'refPrices' => $refPrices,
        ]);
    }

    /**
     * Endpoint kalkulasi penyesuaian harga komersial batubara M6 (D94-4).
     */
    public function adjustPrice(Request $request, AdjustCoalCommercialPriceAction $action)
    {
        $validated = $request->validate([
            'base_price_cents' => 'required|integer|min:1',
            'actual_gar_kcal' => 'required|integer|min:1000',
            'reference_gar_kcal' => 'required|integer|min:1000',
            'actual_ash_permil' => 'required|integer|min:0',
            'base_ash_limit_permil' => 'required|integer|min:1',
            'actual_sulfur_permil' => 'required|integer|min:0',
            'base_sulfur_limit_permil' => 'required|integer|min:1',
            'volume_tons' => 'required|integer|min:1',
        ]);

        $result = $action->execute($validated);

        return response()->json($result);
    }
}
