<?php

declare(strict_types=1);

namespace Modules\Crypto\Domain\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Harga seluruh aset kripto baru saja diperbarui oleh price engine.
 * Modul lain (mis. Finance) memakai event ini untuk memantau risiko.
 */
class PricesTicked implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    /**
     * @param  array<string, string>  $prices  symbol => harga IDR
     */
    public function __construct(
        public readonly array $prices,
    ) {}
}
