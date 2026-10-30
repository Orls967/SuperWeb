<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Math;

/**
 * Result of meter consumption delta analysis.
 */
final readonly class MeterDeltaResult
{
    /**
     * @param  list<MeterInterval>  $intervals  Intervals analyzed
     * @param  int  $totalConsumption  Total valid consumption across all intervals
     * @param  int  $rolloverCount  Number of modulus rollovers detected
     * @param  int  $resetCount  Number of intentional meter resets detected
     * @param  list<string>  $anomalies  Descriptions of detected anomalies
     * @param  bool  $hasAnomalies  Whether any anomalies were detected
     */
    public function __construct(
        public array $intervals,
        public int $totalConsumption,
        public int $rolloverCount,
        public int $resetCount,
        public array $anomalies,
        public bool $hasAnomalies,
    ) {}
}
