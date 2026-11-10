<?php

declare(strict_types=1);

namespace Modules\Treasury\Contracts;

use Modules\Treasury\Domain\Algorithms\RationalRate;

interface ExchangeRateLookup
{
    /**
     * Dapatkan kurs rasional berversi tak dapat diubah untuk mata uang dan tanggal tertentu.
     */
    public function rate(string $ccy, string $date, string $type = 'spot'): RationalRate;
}
