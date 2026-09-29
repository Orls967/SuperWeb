<?php

declare(strict_types=1);

namespace Modules\Crypto\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Crypto\Application\Services\PortfolioService;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Models\CryptoAsset;

class CryptoMarketController extends Controller
{
    public function __construct(
        private readonly PriceFeed $priceFeed,
        private readonly PortfolioService $portfolioService
    ) {}

    public function index(): View
    {
        $assets = CryptoAsset::where('is_active', true)->get();

        $marketData = $assets->map(function (CryptoAsset $asset) {
            $price = $this->priceFeed->currentPrice($asset->symbol);

            return [
                'asset' => $asset,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'current_price' => (string) $price,
                'current_price_formatted' => number_format((float) (string) $price, 0, ',', '.'),
                'change_24h' => $asset->change24h(),
            ];
        });

        return view('crypto::market.index', [
            'marketData' => $marketData,
        ]);
    }

    public function show(string $symbol): View
    {
        $symbol = strtoupper(trim($symbol));
        $asset = CryptoAsset::where('symbol', $symbol)->where('is_active', true)->firstOrFail();
        $price = $this->priceFeed->currentPrice($symbol);

        $initialChartData = $this->portfolioService->getChartData($symbol, '24h');

        $userHolding = '0';
        $userIdrBalance = '0';
        if (auth()->check()) {
            $user = auth()->user();
            $cryptoAcc = $user->walletAccount($symbol);
            $userHolding = $cryptoAcc->cached_balance ?: '0';
            $idrAcc = $user->walletAccount('IDR');
            $userIdrBalance = $idrAcc->cached_balance ?: '0';
        }

        return view('crypto::market.show', [
            'asset' => $asset,
            'currentPrice' => (string) $price,
            'currentPriceFormatted' => number_format((float) (string) $price, 0, ',', '.'),
            'change24h' => $asset->change24h(),
            'chartData' => $initialChartData,
            'userHolding' => $userHolding,
            'userIdrBalance' => $userIdrBalance,
        ]);
    }

    public function pricesApi(): JsonResponse
    {
        $assets = CryptoAsset::where('is_active', true)->get();

        $prices = $assets->mapWithKeys(function (CryptoAsset $asset) {
            $price = $this->priceFeed->currentPrice($asset->symbol);

            return [
                $asset->symbol => [
                    'price' => (string) $price,
                    'price_formatted' => number_format((float) (string) $price, 0, ',', '.'),
                    'change_24h' => $asset->change24h(),
                ],
            ];
        });

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String(),
            'prices' => $prices,
        ]);
    }

    public function chartApi(Request $request, string $symbol): JsonResponse
    {
        $range = $request->query('range', '24h');
        $chartData = $this->portfolioService->getChartData($symbol, (string) $range);

        return response()->json($chartData);
    }
}
