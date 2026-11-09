<?php

declare(strict_types=1);

namespace Modules\Hospital\database\seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Hospital\Domain\Models\Admission;
use Modules\Hospital\Domain\Models\HospitalBed;
use Modules\Hospital\Domain\Models\Patient;
use Modules\Hospital\Domain\Services\PatientPassportService;

final class Phase87Seeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Pengguna Role Rumah Sakit jika belum ada
        $roles = [
            'bed_manager' => 'bed.manager@hospital.test',
            'doctor' => 'dpjp.dr@hospital.test',
            'nurse' => 'head.nurse@hospital.test',
            'rs_admin' => 'admin.rs@hospital.test',
        ];

        foreach ($roles as $role => $email) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => ucfirst(str_replace('_', ' ', $role)),
                    'role' => $role,
                    'password' => bcrypt('password123'),
                ]
            );
        }

        // 2. Buat Fasilitas Rumah Sakit & Ranjang (800 ranjang realistis)
        $hospitals = [
            ['prefix' => 'RS-A', 'name' => 'RSUD Tipe A Kartika Husada', 'beds' => 450],
            ['prefix' => 'RS-B', 'name' => 'RS Graha Medika Swasta', 'beds' => 250],
            ['prefix' => 'RS-C', 'name' => 'RS Darurat Bencana Husada', 'beds' => 100],
        ];

        $classes = ['CLASS_3', 'CLASS_2', 'CLASS_1', 'VIP', 'VVIP', 'ICU', 'NICU', 'PICU', 'HCU', 'ISOLATION'];
        $departments = ['Penyakit Dalam', 'Bedah', 'Anak', 'Kebidanan & Kandungan', 'Jantung', 'Saraf', 'Paru', 'Intensif', 'IGD'];

        $bedInsertBatch = [];
        $bedIdCounter = 1;

        foreach ($hospitals as $hosp) {
            $prefix = $hosp['prefix'];
            $count = $hosp['beds'];

            for ($i = 1; $i <= $count; $i++) {
                $cls = $classes[$i % count($classes)];
                $dept = $departments[$i % count($departments)];
                $floor = (int) (($i / 30) + 1);
                $wing = ($i % 2 === 0) ? 'Timur' : 'Barat';
                $code = sprintf('%s-%s-%03d', $prefix, substr($cls, 0, 3), $i);

                $status = 'AVAILABLE';
                if ($i % 23 === 0) {
                    $status = 'CLEANING';
                } elseif ($i % 47 === 0) {
                    $status = 'MAINTENANCE';
                } elseif ($i % 31 === 0) {
                    $status = 'RESERVED';
                }

                $rate = match ($cls) {
                    'ICU', 'NICU', 'PICU' => 2500000,
                    'ISOLATION' => 1800000,
                    'VVIP' => 3000000,
                    'VIP' => 1500000,
                    'CLASS_1' => 800000,
                    'CLASS_2' => 500000,
                    default => 300000,
                };

                $bedInsertBatch[] = [
                    'bed_code' => $code,
                    'room_number' => "R-{$floor}".sprintf('%02d', ($i % 20) + 1),
                    'ward_name' => $dept,
                    'bed_class' => $cls,
                    'rate_per_day_idr' => $rate,
                    'status' => $status,
                    'created_at' => CarbonImmutable::now()->subDays(30),
                    'updated_at' => CarbonImmutable::now()->subDays(1),
                ];

                if (count($bedInsertBatch) >= 100) {
                    HospitalBed::upsert($bedInsertBatch, ['bed_code'], ['room_number', 'ward_name', 'bed_class', 'rate_per_day_idr', 'status', 'updated_at']);
                    $bedInsertBatch = [];
                }
            }
        }

        if (count($bedInsertBatch) > 0) {
            HospitalBed::upsert($bedInsertBatch, ['bed_code'], ['room_number', 'ward_name', 'bed_class', 'rate_per_day_idr', 'status', 'updated_at']);
        }

        // 3. Pasien & Passport
        $passportService = app(PatientPassportService::class);
        $patientBatch = [];

        for ($p = 1; $p <= 60; $p++) {
            $nik = sprintf('317101%02d08%04d', ($p % 28) + 1, $p + 1000);
            $mrn = sprintf('MRN-2026-%05d', $p);
            $initialHash = hash('sha256', "PASSPORT-GENESIS-{$mrn}");

            $patient = Patient::firstOrCreate(
                ['mrn' => $mrn],
                [
                    'name' => "Pasien Demonstrasi {$p}",
                    'date_of_birth' => CarbonImmutable::now()->subYears(20 + ($p % 50))->format('Y-m-d'),
                    'blood_type' => ['A+', 'B+', 'AB+', 'O+'][$p % 4],
                    'encrypted_allergies' => ($p % 5 === 0) ? ['Penicillin'] : [],
                    'encrypted_chronic_diagnoses' => [],
                    'passport_hash' => $initialHash,
                ]
            );

            // Seed passport genesis jika belum ada
            if ($patient->passportEvents()->count() === 0) {
                $passportService->appendEvent(
                    patientId: $patient->id,
                    eventType: 'PATIENT_REGISTERED',
                    eventPayload: [
                        'mrn' => $patient->mrn,
                        'nik' => $patient->nik,
                        'name' => $patient->name,
                    ],
                    actorId: 1
                );
            }

            // Pasien sebagian admisi aktif
            if ($p <= 25) {
                $occupiedBed = HospitalBed::where('status', 'AVAILABLE')->first();
                if ($occupiedBed) {
                    $encId = DB::table('hsp_encounters')->insertGetId([
                        'encounter_code' => sprintf('ENC-%s-%04d', date('Ymd'), $p),
                        'patient_id' => $patient->id,
                        'encounter_type' => 'INPATIENT',
                        'admitted_at' => CarbonImmutable::now()->subDays($p % 7 + 1),
                        'status' => 'ADMITTED',
                        'created_at' => CarbonImmutable::now()->subDays($p % 7 + 1),
                        'updated_at' => CarbonImmutable::now()->subDays($p % 7 + 1),
                    ]);

                    $occupiedBed->update([
                        'status' => 'OCCUPIED',
                        'current_encounter_id' => $encId,
                    ]);

                    Admission::firstOrCreate(
                        ['patient_id' => $patient->id, 'status' => 'admitted'],
                        [
                            'admission_no' => sprintf('ADM-%s-%04d', date('Ymd'), $p),
                            'encounter_id' => $encId,
                            'bed_id' => $occupiedBed->id,
                            'entitled_class' => $occupiedBed->bed_class,
                            'current_class' => $occupiedBed->bed_class,
                            'deposit_idr' => 3000000,
                            'status' => 'admitted',
                            'admitted_at' => CarbonImmutable::now()->subDays($p % 7 + 1),
                        ]
                    );
                }
            }
        }
    }
}
