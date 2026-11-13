<?php

declare(strict_types=1);

namespace Modules\ControlTower\Domain\Certificates;

final readonly class ForecastAccuracyCertificate
{
    /**
     * @param  list<int>  $history
     */
    public function __construct(
        public string $itemCode,
        public string $model,
        public array $history,
        public int $forecastQty,
        public int $mapeBps,
        public int $madUnits,
        public int $trackingSignalBps,
        public string $digest,
    ) {}
}
