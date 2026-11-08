<?php

declare(strict_types=1);

namespace Modules\Aviation\Integrations;

use Illuminate\Support\Facades\Log;
use Modules\Aviation\Domain\Models\AviationParametricClaim;
use Modules\Insurance\Contracts\ParametricTriggerRecorder;

class InsuranceParametricTriggerService
{
    public function __construct(
        protected ?ParametricTriggerRecorder $recorder = null
    ) {}

    /**
     * Catat pemicu klaim asuransi parametrik otomatis untuk kasus disrupsi penerbangan.
     *
     * @param  int  $disruptionCaseId  ID kasus disrupsi
     * @param  string  $policyNumber  Nomor polis asuransi
     * @param  int  $passengerId  ID penumpang
     * @param  string  $flightNumber  Nomor penerbangan
     * @param  string  $triggerType  Tipe pemicu (VOLCANIC_ERUPTION_CLOSURE, DELAY_OVER_180MIN, dll.)
     * @param  int  $durationMinutes  Durasi keterlambatan dalam menit
     * @param  int  $payoutAmountMinorUnit  Nilai pencairan klaim dalam minor unit
     * @param  array<string, mixed>  $telemetryData  Data bukti radar/NOTAM
     */
    public function recordFlightTrigger(
        int $disruptionCaseId,
        string $policyNumber,
        int $passengerId,
        string $flightNumber,
        string $triggerType,
        int $durationMinutes,
        int $payoutAmountMinorUnit,
        array $telemetryData = []
    ): AviationParametricClaim {
        $externalRef = null;

        if ($this->recorder !== null) {
            try {
                $externalRef = $this->recorder->recordTrigger(
                    $policyNumber,
                    $flightNumber,
                    $triggerType,
                    $durationMinutes,
                    $payoutAmountMinorUnit,
                    $telemetryData
                );
            } catch (\Throwable $e) {
                Log::warning('Panggilan ParametricTriggerRecorder mengalami kendala', ['error' => $e->getMessage()]);
            }
        }

        if ($externalRef === null) {
            $externalRef = sprintf('PTR-FALLBACK-%s-%s', date('Ymd'), strtoupper(substr(uniqid('', true), -6)));
        }

        return AviationParametricClaim::create([
            'disruption_case_id' => $disruptionCaseId,
            'policy_number' => $policyNumber,
            'passenger_party_ref' => (string) $passengerId,
            'trigger_type' => $triggerType,
            'payout_amount_minor_unit' => $payoutAmountMinorUnit,
            'claim_status' => 'approved',
            'external_reference' => $externalRef,
        ]);
    }
}
