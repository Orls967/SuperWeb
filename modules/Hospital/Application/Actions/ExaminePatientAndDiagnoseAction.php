<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Modules\Hospital\Application\DTOs\DoctorExaminationDTO;
use Modules\Hospital\Domain\Models\OutpatientEncounter;

class ExaminePatientAndDiagnoseAction
{
    public function execute(OutpatientEncounter $encounter, DoctorExaminationDTO $dto, ?User $doctorUser = null): OutpatientEncounter
    {
        if ($doctorUser !== null) {
            if (! in_array($doctorUser->role, ['doctor', 'admin'], true)) {
                throw new AuthorizationException('Hanya dokter berwenang melakukan pemeriksaan dan diagnosa.');
            }

            if ($doctorUser->role === 'doctor' && $encounter->doctor && $encounter->doctor->user_id !== $doctorUser->id) {
                throw new AuthorizationException('Dokter tidak berwenang memeriksa pasien dokter lain.');
            }
        }

        if ($encounter->status === 'REGISTERED') {
            throw new DomainException('Pasien belum dipanggil dari antrian.');
        }

        // Validasi format ICD-10 (contoh: K30, I10, A09, J00, E11.9)
        $cleanIcd = trim($dto->icd10DiagnosisCode);
        if (! preg_match('/^[A-Z][0-9]{2}(\.[0-9A-Z]{1,4})?$/i', $cleanIcd)) {
            throw new InvalidArgumentException("Format kode diagnosis ICD-10 '{$cleanIcd}' tidak valid.");
        }

        $encounter->update([
            'encrypted_doctor_notes' => $dto->doctorNotes,
            'encrypted_icd10_code' => strtoupper($cleanIcd),
            'examined_at' => now(),
            'status' => 'EXAMINED',
        ]);

        return $encounter->fresh();
    }
}
