<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\DTOs;

use Carbon\CarbonImmutable;

final class VitalsTelemetryDTO
{
    public function __construct(
        public readonly int $patientId,
        public readonly int $admissionId,
        public readonly ?int $bedId,
        public readonly int $heartRateBpm,
        public readonly int $systolicBpMmhg,
        public readonly int $diastolicBpMmhg,
        public readonly int $respiratoryRateBpm,
        public readonly float $oxygenSaturationPct,
        public readonly float $temperatureCelsius,
        public readonly string $consciousnessLevel, // ALERT, VOICE, PAIN, UNRESPONSIVE
        public readonly bool $supplementalOxygen,
        public readonly CarbonImmutable $recordedAt,
        public readonly ?int $recordedByUserId = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            patientId: (int) ($data['patient_id'] ?? 0),
            admissionId: (int) ($data['admission_id'] ?? 0),
            bedId: isset($data['bed_id']) ? (int) $data['bed_id'] : null,
            heartRateBpm: (int) ($data['heart_rate_bpm'] ?? 75),
            systolicBpMmhg: (int) ($data['systolic_bp_mmhg'] ?? 120),
            diastolicBpMmhg: (int) ($data['diastolic_bp_mmhg'] ?? 80),
            respiratoryRateBpm: (int) ($data['respiratory_rate_bpm'] ?? 16),
            oxygenSaturationPct: (float) ($data['oxygen_saturation_pct'] ?? 98.0),
            temperatureCelsius: (float) ($data['temperature_celsius'] ?? 36.8),
            consciousnessLevel: strtoupper((string) ($data['consciousness_level'] ?? 'ALERT')),
            supplementalOxygen: (bool) ($data['supplemental_oxygen'] ?? false),
            recordedAt: isset($data['recorded_at']) ? CarbonImmutable::parse($data['recorded_at']) : CarbonImmutable::now(),
            recordedByUserId: isset($data['recorded_by_user_id']) ? (int) $data['recorded_by_user_id'] : null
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'patient_id' => $this->patientId,
            'admission_id' => $this->admissionId,
            'bed_id' => $this->bedId,
            'heart_rate_bpm' => $this->heartRateBpm,
            'systolic_bp_mmhg' => $this->systolicBpMmhg,
            'diastolic_bp_mmhg' => $this->diastolicBpMmhg,
            'respiratory_rate_bpm' => $this->respiratoryRateBpm,
            'oxygen_saturation_pct' => $this->oxygenSaturationPct,
            'temperature_celsius' => $this->temperatureCelsius,
            'consciousness_level' => $this->consciousnessLevel,
            'supplemental_oxygen' => $this->supplementalOxygen,
            'recorded_at' => $this->recordedAt->toIso8601String(),
            'recorded_by_user_id' => $this->recordedByUserId,
        ];
    }
}
