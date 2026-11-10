<?php

declare(strict_types=1);

namespace Modules\Treasury\Domain\DTOs;

use Modules\Treasury\Domain\Algorithms\Certificates\SeasonalCashForecastCertificate;

final class ForecastResultDTO
{
    /**
     * @param  list<int>  $seasonalMeans
     * @param  list<int>  $seasonalIndicesPermille
     * @param  list<int>  $projectedWeeks
     */
    public function __construct(
        public float $overallMean,
        public array $seasonalMeans,
        public array $seasonalIndicesPermille,
        public int $interceptNumerator,
        public int $interceptDenominator,
        public int $slopeNumerator,
        public int $slopeDenominator,
        public array $projectedWeeks,
        public SeasonalCashForecastCertificate $certificate
    ) {}
}
