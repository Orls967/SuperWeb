<?php

declare(strict_types=1);

namespace Modules\Crypto\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Enums\TradeStatus;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Crypto\Domain\Models\CryptoTrade;

class PortfolioService
{
    public function __construct(
        private readonly PriceFeed $priceFeed
    ) {}

    public function getPortfolioSummary(User $user): array
    {
        $assets = CryptoAsset::where('is_active', true)->get();
        $items = [];
        $totalCryptoValue = BigDecimal::zero();

        foreach ($assets as $asset) {
            $account = $user->walletAccount($asset->symbol);
            $holding = BigDecimal::of($account->cached_balance ?: '0');
            $currentPrice = $this->priceFeed->currentPrice($asset->symbol);

            $currentValue = $holding->multipliedBy($currentPrice)->toScale(2, RoundingMode::HalfUp);
            $totalCryptoValue = $totalCryptoValue->plus($currentValue);

            // Calculate average buy price
            $buyTrades = CryptoTrade::where('user_id', $user->id)
                ->where('asset_id', $asset->id)
                ->where('side', TradeSide::BUY)
                ->where('status', TradeStatus::COMPLETED)
                ->get();

            $totalBoughtQty = BigDecimal::zero();
            $totalGrossIdr = BigDecimal::zero();

            foreach ($buyTrades as $bt) {
                $totalBoughtQty = $totalBoughtQty->plus(BigDecimal::of($bt->quantity));
                $totalGrossIdr = $totalGrossIdr->plus(BigDecimal::of($bt->gross_idr));
            }

            if (! $totalBoughtQty->isZero()) {
                $avgBuyPrice = $totalGrossIdr->dividedBy($totalBoughtQty, 2, RoundingMode::HalfUp);
            } else {
                $avgBuyPrice = BigDecimal::zero();
            }

            // P/L calculation
            $pnlIdr = BigDecimal::zero();
            $pnlPercent = 0.0;

            if (! $holding->isZero() && ! $avgBuyPrice->isZero()) {
                $costBasis = $holding->multipliedBy($avgBuyPrice);
                $pnlIdr = $currentValue->minus($costBasis)->toScale(2, RoundingMode::HalfUp);

                $diffPrice = $currentPrice->minus($avgBuyPrice);
                $pnlPercent = round(
                    $diffPrice->dividedBy($avgBuyPrice, 4, RoundingMode::HalfUp)->multipliedBy(100)->toFloat(),
                    2
                );
            }

            $items[] = [
                'asset' => $asset,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'holding' => (string) $holding,
                'holding_formatted' => rtrim(rtrim((string) $holding, '0'), '.'),
                'current_price' => (string) $currentPrice,
                'current_price_formatted' => number_format((float) (string) $currentPrice, 0, ',', '.'),
                'current_value' => (string) $currentValue,
                'current_value_formatted' => number_format((float) (string) $currentValue, 0, ',', '.'),
                'avg_buy_price' => (string) $avgBuyPrice,
                'avg_buy_price_formatted' => number_format((float) (string) $avgBuyPrice, 0, ',', '.'),
                'pnl_idr' => (string) $pnlIdr,
                'pnl_idr_formatted' => number_format((float) (string) $pnlIdr, 0, ',', '.'),
                'pnl_percent' => $pnlPercent,
                'change_24h' => $asset->change24h(),
            ];
        }

        $userIdrAccount = $user->walletAccount('IDR');
        $idrBalance = BigDecimal::of($userIdrAccount->cached_balance ?: '0');
        $totalNetWorth = $totalCryptoValue->plus($idrBalance);

        // Calculate asset allocation percentages
        $allocations = [];
        $colors = [
            'BTC' => '#F59E0B',
            'ETH' => '#6366F1',
            'SOL' => '#10B981',
            'BNB' => '#EAB308',
            'USDT' => '#14B8A6',
            'IDR' => '#3B82F6',
        ];

        foreach ($items as $item) {
            $val = BigDecimal::of($item['current_value']);
            $pct = $totalNetWorth->isZero()
                ? 0.0
                : round($val->dividedBy($totalNetWorth, 4, RoundingMode::HalfUp)->multipliedBy(100)->toFloat(), 1);

            if ($pct > 0 || ! BigDecimal::of($item['holding'])->isZero()) {
                $allocations[] = [
                    'symbol' => $item['symbol'],
                    'percentage' => $pct,
                    'value_idr' => (string) $val,
                    'color' => $colors[$item['symbol']] ?? '#9CA3AF',
                ];
            }
        }

        // Add IDR to allocations if non-zero
        if (! $idrBalance->isZero() && ! $totalNetWorth->isZero()) {
            $idrPct = round($idrBalance->dividedBy($totalNetWorth, 4, RoundingMode::HalfUp)->multipliedBy(100)->toFloat(), 1);
            $allocations[] = [
                'symbol' => 'IDR (Kas)',
                'percentage' => $idrPct,
                'value_idr' => (string) $idrBalance,
                'color' => $colors['IDR'],
            ];
        }

        $recentTrades = CryptoTrade::with('asset')
            ->where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->get();

        return [
            'items' => $items,
            'total_crypto_value' => (string) $totalCryptoValue,
            'total_crypto_value_formatted' => number_format((float) (string) $totalCryptoValue, 0, ',', '.'),
            'idr_balance' => (string) $idrBalance,
            'idr_balance_formatted' => number_format((float) (string) $idrBalance, 0, ',', '.'),
            'total_net_worth' => (string) $totalNetWorth,
            'total_net_worth_formatted' => number_format((float) (string) $totalNetWorth, 0, ',', '.'),
            'allocations' => $allocations,
            'recent_trades' => $recentTrades,
        ];
    }

    /**
     * Get historical ticks for chart display (24H, 7D, 30D)
     */
    public function getChartData(string $symbol, string $range = '24h'): array
    {
        $symbol = strtoupper(trim($symbol));
        $asset = CryptoAsset::where('symbol', $symbol)->firstOrFail();

        $query = CryptoPriceTick::where('asset_id', $asset->id);

        $since = match (strtolower($range)) {
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => now()->subHours(24),
        };

        $ticks = $query->where('recorded_at', '>=', $since)
            ->orderBy('recorded_at')
            ->get();

        // Sample ticks if too many
        $maxPoints = 48;
        if ($ticks->count() > $maxPoints) {
            $step = ceil($ticks->count() / $maxPoints);
            $sampled = [];
            foreach ($ticks as $i => $t) {
                if ($i % $step === 0 || $i === $ticks->count() - 1) {
                    $sampled[] = $t;
                }
            }
            $ticks = collect($sampled);
        }

        $labels = [];
        $prices = [];

        foreach ($ticks as $t) {
            $labels[] = strtolower($range) === '24h'
                ? $t->recorded_at->format('H:i')
                : $t->recorded_at->format('d M H:i');
            $prices[] = round((float) $t->price_idr, 2);
        }

        // Include current price if available
        if ($asset->latestTick && (empty($prices) || end($prices) !== round((float) $asset->latestTick->price_idr, 2))) {
            $labels[] = now()->format('H:i');
            $prices[] = round((float) $asset->latestTick->price_idr, 2);
        }

        return [
            'symbol' => $asset->symbol,
            'name' => $asset->name,
            'range' => $range,
            'labels' => $labels,
            'prices' => $prices,
            'current_price' => (float) $asset->currentPrice(),
            'change_24h' => $asset->change24h(),
        ];
    }
}
