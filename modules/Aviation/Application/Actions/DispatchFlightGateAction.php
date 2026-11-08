<?php

declare(strict_types=1);

namespace Modules\Aviation\Application\Actions;

use Carbon\CarbonImmutable;
use Modules\Asset\Contracts\MaintenanceWorkOrderRequester;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Exceptions\AirworthinessCheckFailedException;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessDispatchGate;
use Modules\Shared\Domain\Algorithms\Aviation\AirworthinessResult;

class DispatchFlightGateAction
{
    public function __construct(
        private readonly AirworthinessDispatchGate $airworthinessGate,
        private readonly CalculateWeightAndBalanceAction $wbAction,
        private readonly ?MaintenanceWorkOrderRequester $woRequester = null
    ) {
    }

    public function evaluateEligibility(AviationFlight $flight, ?CarbonImmutable $evalDate = null): AirworthinessResult
    {
        $date = $evalDate ?? CarbonImmutable::now();
        $aircraft = $flight->aircraft;
        if (! $aircraft) {
            throw new \InvalidArgumentException("Penerbangan {$flight->flight_number} belum memiliki pesawat.");
        }

        $duration = $flight->route ? $flight->route->scheduled_duration_minutes : 90;

        return $this->airworthinessGate->evaluate(
            evalDate: $date,
            certExpiry: CarbonImmutable::parse($aircraft->airworthiness_expires_at),
            aCheckDueMinutes: $aircraft->a_check_due_minutes,
            aCheckCurrentMinutes: $aircraft->a_check_current_minutes,
            cyclesLimit: 20000,
            cyclesCurrent: $aircraft->total_flight_cycles,
            flightDurationMinutes: $duration,
            flightCycles: 1,
            melDeferralExpiry: null
        );
    }

    public function execute(
        AviationFlight $flight,
        int $fuelWeightKg,
        int $cargoWeightKg,
        int $paxCount = 100
    ): void {
        $aircraft = $flight->aircraft;
        if (! $aircraft) {
            throw new \InvalidArgumentException("Penerbangan {$flight->flight_number} belum memiliki pesawat.");
        }

        // 1. Evaluasi A2 Kelaikan
        $airworthiness = $this->evaluateEligibility($flight);
        if (! $airworthiness->isPermitted) {
            // Grounded pesawat
            $aircraft->update(['status' => 'grounded']);

            // Minta WO via Asset K9 jika asset_id tersedia
            if ($this->woRequester && $aircraft->asset_id) {
                try {
                    $key = "avi:wo:grounded:{$aircraft->id}:{$flight->id}";
                    $this->woRequester->request(
                        (int) $aircraft->asset_id,
                        'AVI_AIRWORTHINESS_FAILED',
                        "Pesawat {$aircraft->registration_mark} gagal uji kelaikan dispatch: ".implode(', ', $airworthiness->reasons),
                        $key
                    );
                } catch (\Throwable) {
                    // Ignore or log
                }
            }

            throw AirworthinessCheckFailedException::withReasons($aircraft->registration_mark, $airworthiness->reasons);
        }

        // 2. Evaluasi A3 Weight & Balance
        $wbResult = $this->wbAction->execute($flight, $fuelWeightKg, $cargoWeightKg, $paxCount);
        if (! $wbResult->isWithinEnvelope) {
            throw new \RuntimeException('Beban pesawat melanggar batas amplop W&B: '.implode(', ', $wbResult->violations));
        }

        // 3. Lolos kelaikan -> Transisi status
        if ($flight->status === FlightStatus::PLANNED->value) {
            $flight->transitionTo(FlightStatus::SLOT_SECURED, 'DISPATCH_GATE_PASSED');
        }
        if ($flight->status === FlightStatus::SLOT_SECURED->value) {
            $flight->transitionTo(FlightStatus::AIRWORTHY_CLEARED, 'AIRWORTHINESS_AND_WB_CLEARED');
        }
    }
}

