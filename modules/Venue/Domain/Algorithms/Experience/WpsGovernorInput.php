<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Algorithms\Experience;

use InvalidArgumentException;

/**
 * Nilai Masukan Algoritma V15 WaitPerServiceGovernor.
 */
final readonly class WpsGovernorInput
{
    public function __construct(
        public int $stationId,
        public int $activeServersCount,
        public int $arrivalRatePerMinuteBps,
        public int $serviceRatePerMinuteBps,
        public int $maxTolerableWaitMinutes = 15
    ) {
        if ($this->stationId <= 0) {
            throw new InvalidArgumentException('Station ID harus bernilai positif integer.');
        }
        if ($this->activeServersCount <= 0) {
            throw new InvalidArgumentException('Jumlah staf server aktif harus lebih besar dari nol.');
        }
    }
}
