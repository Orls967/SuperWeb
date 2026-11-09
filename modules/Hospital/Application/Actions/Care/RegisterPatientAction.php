<?php

declare(strict_types=1);

namespace Modules\Hospital\Application\Actions\Care;

use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\PatientPassportService;

/**
 * Aksi Pendaftaran Pasien Baru dengan Identitas Kriptografis (Fase 87.1).
 * Melindungi NIK dengan blind index HMAC dan mengenkripsi data medis at-rest (UU PDP).
 */
class RegisterPatientAction
{
    public function __construct(
        protected readonly PatientPassportService $passportService
    ) {}

    /**
     * @param array{
     *     name: string,
     *     nik: string,
     *     date_of_birth: string,
     *     blood_type: string,
     *     party_id?: ?int,
     *     allergies?: list<string>,
     *     chronic_diagnoses?: list<string>,
     *     actor_id?: ?int
     * } $data
     */
    public function execute(array $data): Patient
    {
        $blindIndexNik = hash_hmac('sha256', $data['nik'], config('app.key') ?? 'superweb-secret');

        // Cek duplikasi via blind index
        $existing = Patient::where('blind_index_nik', $blindIndexNik)->first();
        if ($existing !== null) {
            return $existing;
        }

        $mrn = 'MRN-'.date('Ymd').'-'.substr(hash('sha256', $data['nik'].microtime()), 0, 6);

        $patient = Patient::create([
            'mrn' => strtoupper($mrn),
            'name' => $data['name'],
            'date_of_birth' => $data['date_of_birth'],
            'blood_type' => strtoupper($data['blood_type']),
            'blind_index_nik' => $blindIndexNik,
            'party_id' => $data['party_id'] ?? null,
            'encrypted_allergies' => $data['allergies'] ?? [],
            'encrypted_chronic_diagnoses' => $data['chronic_diagnoses'] ?? [],
            'passport_hash' => PatientPassportService::GENESIS_HASH,
        ]);

        // Rekam inisiasi paspor kesehatan (genesis block)
        $this->passportService->recordEvent(
            $patient,
            'PATIENT_REGISTRATION',
            [
                'action' => 'INITIAL_KYC_REGISTRATION',
                'blood_type' => $patient->blood_type,
                'initial_allergies_count' => count($data['allergies'] ?? []),
                'initial_chronic_count' => count($data['chronic_diagnoses'] ?? []),
            ],
            $data['actor_id'] ?? null
        );

        return $patient->fresh();
    }
}
