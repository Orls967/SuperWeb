<?php

declare(strict_types=1);

namespace Modules\Rwa\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Rwa\Application\Services\RwaTokenService;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;

class RwaController extends Controller
{
    public function index(Request $request): View
    {
        $assets = RwaAsset::with('holdings')->latest()->take(10)->get();
        $holdings = RwaHolding::with('asset')->latest()->take(10)->get();
        $dividends = RwaDividend::with('asset')->latest()->take(10)->get();

        return view('rwa::rwa.index', compact('assets', 'holdings', 'dividends'));
    }

    public function distribute(
        Request $request,
        RwaAsset $asset,
        RwaTokenService $service
    ): RedirectResponse {
        $validated = $request->validate([
            'revenue_pool_idr' => 'required|integer|min:1000',
        ]);

        $dividend = $service->distributeDailyDividend(
            $asset,
            (int) $validated['revenue_pool_idr'],
            now()->toDateString()
        );

        return redirect()->route('rwa.index')->with('success', "Daily dividend distributed for {$asset->token_symbol}. Total: Rp ".number_format($dividend->distributed_idr));
    }
}
