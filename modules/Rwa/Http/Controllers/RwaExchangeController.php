<?php

declare(strict_types=1);

namespace Modules\Rwa\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Rwa\Application\Actions\DistributeProRataRwaRentalYieldAction;
use Modules\Rwa\Application\Actions\MatchSecondaryRwaOrderbookTradeAction;
use Modules\Rwa\Application\Actions\SubscribePrimaryRwaEscrowOfferingAction;
use Modules\Rwa\Domain\Models\RwaAsset;
use Modules\Rwa\Domain\Models\RwaDividend;
use Modules\Rwa\Domain\Models\RwaHolding;
use Modules\Rwa\Domain\Models\RwaOffering;
use Modules\Rwa\Domain\Models\RwaOrderbookOrder;

class RwaExchangeController extends Controller
{
    public function offerings(Request $request): View
    {
        $offerings = RwaOffering::with('asset')->latest()->paginate(15);
        $assets = RwaAsset::all();

        return view('rwa::rwa.offerings', compact('offerings', 'assets'));
    }

    public function orderbook(Request $request): View
    {
        $buyOrders = RwaOrderbookOrder::where('side', 'BUY')->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])->orderByDesc('price_per_token_idr')->get();
        $sellOrders = RwaOrderbookOrder::where('side', 'SELL')->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])->orderBy('price_per_token_idr')->get();

        return view('rwa::rwa.orderbook', compact('buyOrders', 'sellOrders'));
    }

    public function yieldDistribution(Request $request): View
    {
        $dividends = RwaDividend::with('asset')->latest()->paginate(15);
        $assets = RwaAsset::all();

        return view('rwa::rwa.yield_distribution', compact('dividends', 'assets'));
    }

    public function holdings(Request $request): View
    {
        $holdings = RwaHolding::with('asset', 'user')->latest()->paginate(20);

        return view('rwa::rwa.holdings', compact('holdings'));
    }

    public function subscribeOffering(
        Request $request,
        SubscribePrimaryRwaEscrowOfferingAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'offering_id' => 'required|integer',
            'quantity' => 'required|integer|min:1',
        ]);

        $userId = auth()->id() ?? 1;
        $result = $action->handle((int) $validated['offering_id'], $userId, (int) $validated['quantity']);

        return redirect()->route('rwa.exchange.offerings')
            ->with('success', "Subscribed {$result['tokens_subscribed']} tokens for IDR ".number_format($result['total_cost_idr']));
    }

    public function matchTrade(
        Request $request,
        MatchSecondaryRwaOrderbookTradeAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'buy_order_id' => 'required|integer',
            'sell_order_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['buy_order_id'], (int) $validated['sell_order_id']);

        return redirect()->route('rwa.exchange.orderbook')
            ->with('success', "Trade matched: {$result['matched_qty']} tokens executed at IDR ".number_format($result['trade_price_idr']).' per token');
    }

    public function distributeYield(
        Request $request,
        DistributeProRataRwaRentalYieldAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'rwa_asset_id' => 'required|integer',
            'revenue_pool_idr' => 'required|integer|min:1000',
        ]);

        $result = $action->handle((int) $validated['rwa_asset_id'], (int) $validated['revenue_pool_idr']);

        return redirect()->route('rwa.exchange.yield-distribution')
            ->with('success', 'Distributed IDR '.number_format($result['distributed_idr'])." to {$result['recipient_count']} holders (Reserve: IDR ".number_format($result['rounding_reserve_idr']).')');
    }
}
