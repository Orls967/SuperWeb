<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\PatientMedicalRecord;
use Tests\TestCase;

class PatientMedicalRecordEncryptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_medical_fields_are_encrypted_in_database(): void
    {
        $record = PatientMedicalRecord::query()->create([
            'patient_mrn' => 'MRN-2026-0001',
            'national_id_nik' => '3171012345670001',
            'full_name' => 'Budi Santoso',
            'icd10_diagnosis_code' => 'I10-ESSENTIAL-HYPERTENSION',
            'prescription_details' => 'Amlodipine 10mg daily',
            'treating_doctor_id' => null,
        ]);

        // Eloquent model decrypts transparently
        $this->assertSame('3171012345670001', $record->national_id_nik);
        $this->assertSame('I10-ESSENTIAL-HYPERTENSION', $record->icd10_diagnosis_code);

        // Raw database query contains encrypted ciphertext (starts with base64 payload / eyJpdiI)
        $raw = DB::table('hsp_patient_records')->where('id', $record->id)->first();
        $this->assertNotSame('3171012345670001', $raw->national_id_nik);
        $this->assertNotSame('I10-ESSENTIAL-HYPERTENSION', $raw->icd10_diagnosis_code);
        $this->assertStringStartsWith('eyJ', (string) $raw->national_id_nik);
    }
}
