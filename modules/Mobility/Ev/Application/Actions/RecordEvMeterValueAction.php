<?php

declare(strict_types=1);

namespace Modules\Mobility\Ev\Application\Actions;

use Carbon\CarbonInterface;
use Modules\Mobility\Ev\Domain\Models\EvMeterValue;
use Modules\Mobility\Ev\Domain\Models\EvSession;

class RecordEvMeterValueAction
{
    public function execute(
        int $sessionId,
        CarbonInterface $readAt,
        int $energyWh,
        int $powerW = 0
    ): EvMeterValue {
        $lastMeter = EvMeterValue::where('session_id', $sessionId)
            ->orderBy('read_at', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($lastMeter !== null && $energyWh < $lastMeter->energy_wh) {
            throw new \InvalidArgumentException('Meter value must be monotonically non-decreasing');
        }

        $idempotencyKey = "meter:{$sessionId}:{$readAt->timestamp}";

        $meter = EvMeterValue::firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'session_id' => $sessionId,
                'read_at' => $readAt,
                'energy_wh' => $energyWh,
                'power_w' => $powerW,
            ]
        );

        // Update total energy_wh di session
        EvSession::where('id', $sessionId)->update([
            'energy_wh' => $energyWh,
        ]);

        return $meter;
    }
}
