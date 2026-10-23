<?php

declare(strict_types=1);

namespace Modules\Hospital\database\seeders;

use Illuminate\Database\Seeder;
use Modules\Hospital\Domain\Models\ClinicalTrial;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Models\TrialSubject;

class HospitalDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create hospital beds
        HospitalBed::firstOrCreate(
            ['bed_code' => 'BED-ICU-01'],
            [
                'ward_name' => 'Intensive Care Unit',
                'room_number' => 'ICU-101',
                'bed_class' => 'VVIP',
                'rate_per_day_idr' => 2500000,
                'status' => 'AVAILABLE',
            ]
        );

        HospitalBed::firstOrCreate(
            ['bed_code' => 'BED-VIP-01'],
            [
                'ward_name' => 'Paviliun Melati',
                'room_number' => 'VIP-201',
                'bed_class' => 'VIP',
                'rate_per_day_idr' => 1500000,
                'status' => 'AVAILABLE',
            ]
        );

        // 2. Create demo patient
        $patient = Patient::firstOrCreate(
            ['mrn' => 'MRN-HSP-001'],
            [
                'name' => 'Budi Santoso',
                'date_of_birth' => '1985-05-12',
                'blood_type' => 'O+',
                'encrypted_allergies' => ['Penicillin'],
                'encrypted_chronic_diagnoses' => ['Hypertension'],
                'passport_hash' => hash('sha256', 'PASSPORT-MRN-HSP-001'),
            ]
        );

        // 3. Create clinical trial
        $trial = ClinicalTrial::firstOrCreate(
            ['trial_code' => 'TRL-CARDIO-01'],
            [
                'title' => 'Phase 3 Multicenter Cardiovascular Drug Study',
                'phase' => 'PHASE_3',
                'sponsor_name' => 'Novartis Global Research',
                'target_subjects' => 100,
                'status' => 'RECRUITING',
            ]
        );

        // 4. Enroll subject
        TrialSubject::firstOrCreate(
            ['trial_id' => $trial->id, 'patient_id' => $patient->id],
            [
                'subject_code' => 'SUBJ-CARDIO-001',
                'anonymized_hash' => hash('sha256', "ANON-{$patient->mrn}"),
                'informed_consent_hash' => hash('sha256', 'CONSENT-VERIFIED'),
                'randomized_arm' => 'ARM_A_ACTIVE',
                'status' => 'ENROLLED',
            ]
        );
    }
}
