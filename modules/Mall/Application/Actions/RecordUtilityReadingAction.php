<?php

declare(strict_types=1);

namespace Modules\Mall\Application\Actions;

use Modules\Mall\Application\Services\UtilityTariffCalculator;
use Modules\Mall\Domain\Enums\UtilityType;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\UtilityReading;

class RecordUtilityReadingAction
{
    public function __construct(
        protected UtilityTariffCalculator $calculator,
    ) {}

    public function execute(
        Lease $lease,
        string $periodMonth,
        UtilityType $type,
        float $currentMeter,
        ?float $previousMeter = null,
        ?int $recordedBy = null
    ): UtilityReading {
        if ($previousMeter === null) {
            // Cek pembacaan meteran periode sebelumnya
            $prevMonth = date('Y-m', strtotime($periodMonth.'-01 -1 month'));
            $lastReading = UtilityReading::where('lease_id', $lease->id)
                ->where('utility_type', $type)
                ->where('period_month', $prevMonth)
                ->first();

            $previousMeter = $lastReading ? (float) $lastReading->current_meter : 0.0;
        }

        $usage = max(0.0, $currentMeter - $previousMeter);
        $amount = $this->calculator->calculate($lease->property_id, $type, $usage);

        return UtilityReading::updateOrCreate(
            [
                'lease_id' => $lease->id,
                'period_month' => $periodMonth,
                'utility_type' => $type,
            ],
            [
                'unit_id' => $lease->unit_id,
                'previous_meter' => $previousMeter,
                'current_meter' => $currentMeter,
                'usage' => $usage,
                'amount' => $amount,
                'recorded_by' => $recordedBy,
                'recorded_at' => now(),
            ]
        );
    }
}
