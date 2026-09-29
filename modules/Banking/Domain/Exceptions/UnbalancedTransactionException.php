<?php

declare(strict_types=1);

namespace Modules\Banking\Domain\Exceptions;

use RuntimeException;

class UnbalancedTransactionException extends RuntimeException
{
    public function __construct(string $assetCode, string $sum)
    {
        parent::__construct("Transaksi tidak seimbang untuk aset {$assetCode}. Jumlah selisih: {$sum} (harus bernilai 0).");
    }
}
