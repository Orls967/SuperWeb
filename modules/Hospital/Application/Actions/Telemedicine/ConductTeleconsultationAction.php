<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Telemedicine;

use Modules\Hospital\Domain\Models\TeleConsult;
use Modules\Hospital\Domain\Models\TeleConsultNote;

class ConductTeleconsultationAction
{
    /**
     * Menyimpan hasil SOAP konsultasi telemedis secara terenkripsi.
     *
     * @param array{
     *     tele_consult_id: int,
     *     subjective: string,
     *     objective: string,
     *     assessment: string,
     *     plan: string,
     *     icd10_diagnosis_code: string,
     *     doctor_token: string
     * } $soapData
     */
    public function execute(array $soapData): TeleConsultNote
    {
        $consult = TeleConsult::findOrFail((int) $soapData['tele_consult_id']);

        $note = TeleConsultNote::create([
            'consultation_id' => $consult->id,
            'subjective_encrypted' => $soapData['subjective'],
            'objective_encrypted' => $soapData['objective'],
            'assessment_encrypted' => $soapData['assessment'],
            'plan_encrypted' => $soapData['plan'],
            'icd10_diagnosis_code' => strtoupper(trim($soapData['icd10_diagnosis_code'])),
            'signed_doctor_token' => hash('sha256', $soapData['doctor_token']),
        ]);

        $consult->status = 'IN_PROGRESS';
        $consult->clinical_notes .= "\n[SOAP Recorded] ICD-10: ".$note->icd10_diagnosis_code;
        $consult->save();

        return $note;
    }
}
