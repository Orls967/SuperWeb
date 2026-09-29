<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Models;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CryptoAsset extends Model
{
    protected $table = 'crypto_assets';

    protected $fillable = [
        'symbol',
        'name',
        'decimals',
        'volatility',
        'is_active',
        'icon',
    ];

    protected $casts = [
        'decimals' => 'integer',
        'volatility' => 'float',
        'is_active' => 'boolean',
    ];

    public function ticks(): HasMany
    {
        return $this->hasMany(CryptoPriceTick::class, 'asset_id');
    }

    public function latestTick(): HasOne
    {
        return $this->hasOne(CryptoPriceTick::class, 'asset_id')->latestOfMany('recorded_at');
    }

    public function trades(): HasMany
    {
        return $this->hasMany(CryptoTrade::class, 'asset_id');
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(CryptoAlert::class, 'asset_id');
    }

    public function currentPrice(): string
    {
        $tick = $this->latestTick;

        return $tick ? (string) $tick->price_idr : '0';
    }

    public function currentPriceBigDecimal(): BigDecimal
    {
        return BigDecimal::of($this->currentPrice());
    }

    /**
     * 24h price change percentage
     */
    public function change24h(): float
    {
        $current = $this->currentPriceBigDecimal();
        if ($current->isZero()) {
            return 0.0;
        }

        $tick24hAgo = $this->ticks()
            ->where('recorded_at', '<=', now()->subHours(24))
            ->orderByDesc('recorded_at')
            ->first();

        if (! $tick24hAgo) {
            $tick24hAgo = $this->ticks()->orderBy('recorded_at')->first();
        }

        if (! $tick24hAgo) {
            return 0.0;
        }

        $oldPrice = BigDecimal::of($tick24hAgo->price_idr);
        if ($oldPrice->isZero()) {
            return 0.0;
        }

        $diff = $current->minus($oldPrice);
        $percent = $diff->dividedBy($oldPrice, 6, RoundingMode::HalfUp)->multipliedBy(100);

        return round($percent->toFloat(), 2);
    }
}
