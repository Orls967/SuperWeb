<?php

declare(strict_types=1);

namespace Modules\Crypto\Contracts;

use App\Models\User;
use Brick\Math\BigDecimal;
use Modules\Crypto\Domain\Enums\TradeSide;
use Modules\Crypto\Domain\Models\CryptoPriceTick;
use Modules\Crypto\Domain\Models\CryptoQuote;

interface PriceFeed
{
    /**
     * Get current price of crypto asset symbol in IDR as BigDecimal
     */
    public function currentPrice(string $symbol): BigDecimal;

    /**
     * Get latest recorded price tick
     */
    public function latestTick(string $symbol): ?CryptoPriceTick;

    /**
     * Generate locked price quote valid for 15 seconds
     */
    public function generateQuote(
        User $user,
        string $symbol,
        TradeSide $side,
        ?string $amountIdr = null,
        ?string $cryptoQty = null
    ): CryptoQuote;
}
