<?php

declare(strict_types=1);

namespace Modules\Crypto\Application\Services;

use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Str;
use Modules\Crypto\Contracts\PriceFeed;
use Modules\Crypto\Domain\Enums\AlertCondition;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Events\PricesTicked;
use Modules\Crypto\Domain\Models\CryptoAlert;
use Modules\Crypto\Domain\Models\CryptoAsset;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Crypto\Domain\Models\CryptoQuote;

class PriceEngineService implements PriceFeed
{
    public const FEE_PERCENT = '0.002'; // 0.2%

    public function currentPrice(string $symbol): BigDecimal
    {
        $symbol = strtoupper(trim($symbol));
        $asset = CryptoAsset::where('symbol', $symbol)->first();

        if (! $asset) {
            return BigDecimal::zero();
        }

        $tick = $asset->latestTick;
        if (! $tick) {
            return BigDecimal::of($this->getBasePriceFor($symbol));
        }

        return BigDecimal::of((string) $tick->price_idr);
    }

    public function latestTick(string $symbol): ?CryptoPriceTick
    {
        $symbol = strtoupper(trim($symbol));
        $asset = CryptoAsset::where('symbol', $symbol)->first();

        return $asset?->latestTick;
    }

    public function generateQuote(
        User $user,
        string $symbol,
        TradeSide $side,
        ?string $amountIdr = null,
        ?string $cryptoQty = null
    ): CryptoQuote {
        $symbol = strtoupper(trim($symbol));
        $asset = CryptoAsset::where('symbol', $symbol)->where('is_active', true)->firstOrFail();

        $price = $this->currentPrice($symbol);
        if ($price->isZero()) {
            $price = BigDecimal::of($this->getBasePriceFor($symbol));
        }

        $feeRate = BigDecimal::of(self::FEE_PERCENT);

        if ($amountIdr !== null && $amountIdr !== '') {
            $grossIdr = BigDecimal::of($amountIdr)->toScale(2, RoundingMode::Down);
            $quantity = $grossIdr->dividedBy($price, (int) $asset->decimals, RoundingMode::Down);
            $feeIdr = $grossIdr->multipliedBy($feeRate)->toScale(2, RoundingMode::HalfUp);
        } elseif ($cryptoQty !== null && $cryptoQty !== '') {
            $quantity = BigDecimal::of($cryptoQty)->toScale((int) $asset->decimals, RoundingMode::Down);
            $grossIdr = $quantity->multipliedBy($price)->toScale(2, RoundingMode::Down);
            $feeIdr = $grossIdr->multipliedBy($feeRate)->toScale(2, RoundingMode::HalfUp);
        } else {
            throw new \InvalidArgumentException('Nominal IDR atau jumlah koin wajib diisi.');
        }

        return CryptoQuote::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'asset_id' => $asset->id,
            'side' => $side,
            'price_idr' => (string) $price,
            'quantity' => (string) $quantity,
            'gross_idr' => (string) $grossIdr,
            'fee_idr' => (string) $feeIdr,
            'expires_at' => now()->addSeconds(15),
            'is_executed' => false,
        ]);
    }

    /**
     * Run simulation tick for all active assets
     */
    public function tick(): array
    {
        $assets = CryptoAsset::where('is_active', true)->get();
        $results = [];

        foreach ($assets as $asset) {
            $currentPrice = $this->currentPrice($asset->symbol);
            $newPrice = $this->calculateNextPrice($asset, $currentPrice);

            $tick = CryptoPriceTick::create([
                'asset_id' => $asset->id,
                'price_idr' => (string) $newPrice,
                'recorded_at' => now(),
            ]);

            $this->checkAlerts($asset, $newPrice);

            $results[$asset->symbol] = (string) $newPrice;
        }

        // Modul lain (mis. Finance risk monitor) bereaksi terhadap harga terbaru
        PricesTicked::dispatch($results);

        return $results;
    }

    private function calculateNextPrice(CryptoAsset $asset, BigDecimal $currentPrice): BigDecimal
    {
        if ($asset->symbol === 'USDT') {
            // USDT stable around 16,200 with ±0.1% fluctuation
            $base = 16200.0;
            $drift = (mt_rand(-10, 10) / 10000.0); // ±0.1%
            $val = round($base * (1.0 + $drift), 2);

            return BigDecimal::of((string) $val);
        }

        if ($currentPrice->isZero()) {
            $currentPrice = BigDecimal::of($this->getBasePriceFor($asset->symbol));
        }

        $currentFloat = (float) (string) $currentPrice;
        $volatility = $asset->volatility ?: 0.02;

        // Realistic 1-minute random walk with small drift
        $drift = (mt_rand(-20, 25) / 100000.0);
        $shock = ((mt_rand(-100, 100) / 100.0) * $volatility * 0.05);

        $newFloat = $currentFloat * (1.0 + $drift + $shock);

        // Don't let prices drop below 10% of base
        $minPrice = $this->getBasePriceFor($asset->symbol) * 0.1;
        if ($newFloat < $minPrice) {
            $newFloat = $minPrice;
        }

        return BigDecimal::of((string) round($newFloat, 2));
    }

    private function checkAlerts(CryptoAsset $asset, BigDecimal $price): void
    {
        $alerts = CryptoAlert::where('asset_id', $asset->id)
            ->where('is_triggered', false)
            ->get();

        foreach ($alerts as $alert) {
            $target = BigDecimal::of($alert->target_price_idr);
            $triggered = false;

            if ($alert->condition === AlertCondition::ABOVE && $price->isGreaterThanOrEqualTo($target)) {
                $triggered = true;
            } elseif ($alert->condition === AlertCondition::BELOW && $price->isLessThanOrEqualTo($target)) {
                $triggered = true;
            }

            if ($triggered) {
                $alert->update([
                    'is_triggered' => true,
                    'triggered_at' => now(),
                ]);
            }
        }
    }

    public function getBasePriceFor(string $symbol): float
    {
        return match (strtoupper($symbol)) {
            'BTC' => 1550000000.0,
            'ETH' => 52000000.0,
            'SOL' => 2800000.0,
            'BNB' => 9200000.0,
            'USDT' => 16200.0,
            default => 100000.0,
        };
    }
}
