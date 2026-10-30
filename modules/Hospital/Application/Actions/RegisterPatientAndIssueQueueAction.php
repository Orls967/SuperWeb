<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions;

use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Hospital\Application\DTOs\RegisterPatientDTO;
use Modules\Hospital\Domain\Models\Doctor;
use Modules\Hospital\Domain\Models\OutpatientEncounter;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\Polyclinic;
use Modules\Hospital\Domain\Models\QueueTicket;

class RegisterPatientAndIssueQueueAction
{
    /**
     * @return array{patient: Patient, queue: QueueTicket, encounter: OutpatientEncounter}
     */
    public function execute(RegisterPatientDTO $dto, ?User $staff = null): array
    {
        if (trim($dto->nik) === '') {
            throw new InvalidArgumentException('NIK pasien wajib diisi.');
        }

        if ($dto->paymentMethod === 'BPJS' && empty($dto->bpjsCardNumber)) {
            throw new InvalidArgumentException('Nomor kartu BPJS wajib diisi untuk pasien penjamin JKN/BPJS.');
        }

        $blindIndex = hash_hmac('sha256', $dto->nik, (string) config('app.blind_index_pepper', 'hospital-rawat-jalan-secret-pepper-2026'));
        $today = now()->toDateString();

        return DB::transaction(function () use ($dto, $blindIndex, $today) {
            $patient = Patient::where('nik_bidx', $blindIndex)->orWhere('nik_blind_index', $blindIndex)->first();

            if ($patient !== null) {
                // Cek apakah pasien sudah terdaftar di poli yang sama hari ini
                $alreadyRegistered = OutpatientEncounter::where('patient_id', $patient->id)
                    ->where('polyclinic_id', $dto->polyclinicId)
                    ->whereDate('created_at', $today)
                    ->where('status', '!=', 'CANCELLED')
                    ->exists();

                if ($alreadyRegistered) {
                    throw new DomainException('Pasien dengan NIK tersebut sudah terdaftar pada poliklinik ini hari ini.');
                }
            } else {
                $mrn = sprintf('MRN-%s-%04d', now()->format('Ymd'), (Patient::count() + 1));
                $passportHash = hash('sha256', "{$mrn}:{$dto->fullName}:{$dto->dateOfBirth->toDateString()}:{$dto->bloodType}");

                $patient = Patient::create([
                    'mrn' => $mrn,
                    'name' => $dto->fullName,
                    'date_of_birth' => $dto->dateOfBirth->toDateString(),
                    'blood_type' => $dto->bloodType,
                    'passport_hash' => $passportHash,
                    'national_id_nik' => $dto->nik,
                    'nik_bidx' => $blindIndex,
                    'user_id' => $dto->userId,
                ]);
            }

            // Lock atomic untuk penghitungan nomor antrian per poli per hari
            $maxQueue = QueueTicket::where('polyclinic_id', $dto->polyclinicId)
                ->whereDate('queue_date', $today)
                ->lockForUpdate()
                ->max('queue_number');

            $nextNumber = ($maxQueue !== null ? (int) $maxQueue : 0) + 1;

            $polyclinic = Polyclinic::findOrFail($dto->polyclinicId);
            $queueCode = sprintf('%s-%s-%03d', $polyclinic->code, now()->format('Ymd'), $nextNumber);

            $queue = QueueTicket::create([
                'polyclinic_id' => $dto->polyclinicId,
                'doctor_id' => $dto->doctorId,
                'queue_date' => $today,
                'queue_number' => $nextNumber,
                'queue_code' => $queueCode,
                'status' => 'WAITING',
            ]);

            $encounterCode = sprintf('ENC-%s-%04d', now()->format('Ymd'), (OutpatientEncounter::count() + 1));

            $encounter = OutpatientEncounter::create([
                'encounter_code' => $encounterCode,
                'patient_id' => $patient->id,
                'queue_id' => $queue->id,
                'polyclinic_id' => $dto->polyclinicId,
                'doctor_id' => $dto->doctorId,
                'payment_method' => $dto->paymentMethod,
                'bpjs_card_number' => $dto->bpjsCardNumber,
                'status' => 'REGISTERED',
            ]);

            return [
                'patient' => $patient,
                'queue' => $queue,
                'encounter' => $encounter,
            ];
        });
    }
}
