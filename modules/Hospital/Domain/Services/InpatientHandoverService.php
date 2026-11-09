<?php

declare(strict_types=1);

namespace Modules\Hospital\Domain\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\Patient;

final class InpatientHandoverService
{
    /**
     * Mencatat serah terima tugas jaga perawat / dokter dengan protokol SBAR
     * (Situation, Background, Assessment, Recommendation).
     *
     * @param array{
     *     admission_id: int,
     *     patient_id: int,
     *     shift_name: string,
     *     departing_nurse_id: int,
     *     incoming_nurse_id: int,
     *     situation: string,
     *     background: string,
     *     assessment: string,
     *     recommendation: string,
     *     acuity_grade: string
     * } $data
     * @return array<string, mixed>
     */
    public function recordHandover(array $data): array
    {
        $handoverHash = hash('sha256', json_encode([
            'admission_id' => $data['admission_id'],
            'shift' => $data['shift_name'],
            'departing' => $data['departing_nurse_id'],
            'incoming' => $data['incoming_nurse_id'],
            'sbar' => $data['situation'] . $data['background'] . $data['assessment'] . $data['recommendation'],
            'timestamp' => CarbonImmutable::now()->toIso8601String(),
        ], JSON_UNESCAPED_SLASHES));

        // Catat ke patient passport events untuk audit trail kepatuhan handover
        $passportService = app(PatientPassportService::class);
        $passportService->appendEvent(
            patientId: $data['patient_id'],
            eventType: 'SHIFT_HANDOVER_SBAR',
            eventPayload: [
                'admission_id' => $data['admission_id'],
                'shift_name' => $data['shift_name'],
                'departing_nurse_id' => $data['departing_nurse_id'],
                'incoming_nurse_id' => $data['incoming_nurse_id'],
                'situation' => $data['situation'],
                'assessment' => $data['assessment'],
                'recommendation' => $data['recommendation'],
                'acuity_grade' => $data['acuity_grade'],
                'handover_hash' => $handoverHash,
            ],
            actorId: $data['departing_nurse_id']
        );

        return [
            'success' => true,
            'handover_hash' => $handoverHash,
            'shift_name' => $data['shift_name'],
            'recorded_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }
}
