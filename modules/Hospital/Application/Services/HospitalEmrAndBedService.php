<?php

namespace Modules\Hospital\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\Encounter;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\HospitalOrder;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\VitalsTelemetry;

class HospitalEmrAndBedService
{
    /**
     * 87.2 Register Patient with Cryptographic Health Passport
     */
    public function registerPatientWithPassport(
        string $mrn,
        string $name,
        Carbon $dob,
        string $bloodType,
        array $allergies,
        array $chronicDiagnoses
    ): Patient {
        $passportHash = hash('sha256', "{$mrn}:{$name}:{$dob->toDateString()}:{$bloodType}:".json_encode($allergies).':'.json_encode($chronicDiagnoses));

        return Patient::create([
            'mrn' => $mrn,
            'name' => $name,
            'date_of_birth' => $dob->toDateString(),
            'blood_type' => $bloodType,
            'encrypted_allergies' => $allergies,
            'encrypted_chronic_diagnoses' => $chronicDiagnoses,
            'passport_hash' => $passportHash,
        ]);
    }

    /**
     * 87.3 Bed management: Allocate bed anti-bentrok with lockForUpdate
     */
    public function allocateBed(HospitalBed $bed, Encounter $encounter): HospitalBed
    {
        return DB::transaction(function () use ($bed, $encounter) {
            $lockedBed = HospitalBed::where('id', $bed->id)->lockForUpdate()->firstOrFail();

            if ($lockedBed->status !== 'AVAILABLE') {
                throw new \RuntimeException("Bed {$lockedBed->bed_code} is not available (Status: {$lockedBed->status})");
            }

            $lockedBed->update([
                'status' => 'OCCUPIED',
                'current_encounter_id' => $encounter->id,
            ]);

            return $lockedBed;
        });
    }

    /**
     * Discharge patient from bed and put bed in cleaning queue
     */
    public function dischargeBed(HospitalBed $bed): HospitalBed
    {
        return DB::transaction(function () use ($bed) {
            $lockedBed = HospitalBed::where('id', $bed->id)->lockForUpdate()->firstOrFail();

            $lockedBed->update([
                'status' => 'CLEANING',
                'current_encounter_id' => null,
            ]);

            return $lockedBed;
        });
    }

    /**
     * 87.4 Clinical Pathway: Order sequencing & delay alert check
     */
    public function checkClinicalPathwayDelays(Encounter $encounter, Carbon $asOfTime): array
    {
        $overdueOrders = HospitalOrder::where('encounter_id', $encounter->id)
            ->where('status', 'PENDING')
            ->where('scheduled_at', '<', $asOfTime)
            ->get();

        $alerts = [];
        foreach ($overdueOrders as $order) {
            if (! $order->delay_alert_sent) {
                $order->update([
                    'status' => 'DELAYED',
                    'delay_alert_sent' => true,
                ]);
                $alerts[] = "Delay alert: {$order->order_type} order {$order->order_code} overdue for patient {$encounter->patient->mrn}";
            }
        }

        return $alerts;
    }

    /**
     * 87.5 Ingest IoT ICU/Telemetry vitals & trigger Code Blue alert on critical thresholds
     */
    public function ingestVitalsTelemetry(
        Encounter $encounter,
        float $spo2,
        int $heartRate,
        float $tempC,
        Carbon $recordedAt
    ): VitalsTelemetry {
        $isCodeBlue = ($spo2 < 85.0 || $heartRate < 40 || $heartRate > 150);
        $proofHash = hash('sha256', "{$encounter->id}:{$spo2}:{$heartRate}:{$tempC}:{$recordedAt->toIso8601String()}");

        return VitalsTelemetry::create([
            'encounter_id' => $encounter->id,
            'spo2_percent' => $spo2,
            'heart_rate_bpm' => $heartRate,
            'temp_c' => $tempC,
            'code_blue_triggered' => $isCodeBlue,
            'proof_hash' => $proofHash,
            'recorded_at' => $recordedAt,
        ]);
    }
}
