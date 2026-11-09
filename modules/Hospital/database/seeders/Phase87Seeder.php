<?php

declare(strict_types=1);

namespace Modules\Hospital\database\seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
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
                $code = sprintf("%s-%s-%03d", $prefix, substr($cls, 0, 3), $i);

                $status = 'AVAILABLE';
                if ($i % 7 === 0) {
                    $status = 'OCCUPIED';
                } elseif ($i % 23 === 0) {
                    $status = 'CLEANING';
                } elseif ($i % 47 === 0) {
                    $status = 'MAINTENANCE';
                } elseif ($i % 31 === 0) {
                    $status = 'RESERVED';
                }

                $bedInsertBatch[] = [
                    'bed_code' => $code,
                    'room_number' => "R-{$floor}" . sprintf("%02d", ($i % 20) + 1),
                    'department' => $dept,
                    'class' => $cls,
                    'status' => $status,
                    'floor' => $floor,
                    'wing' => $wing,
                    'created_at' => CarbonImmutable::now()->subDays(30),
                    'updated_at' => CarbonImmutable::now()->subDays(1),
                ];

                if (count($bedInsertBatch) >= 100) {
                    HospitalBed::upsert($bedInsertBatch, ['bed_code'], ['room_number', 'department', 'class', 'status', 'floor', 'wing', 'updated_at']);
                    $bedInsertBatch = [];
                }
            }
        }

        if (count($bedInsertBatch) > 0) {
            HospitalBed::upsert($bedInsertBatch, ['bed_code'], ['room_number', 'department', 'class', 'status', 'floor', 'wing', 'updated_at']);
        }

        // 3. Pasien & Passport
        $passportService = app(PatientPassportService::class);
        $patientBatch = [];

        for ($p = 1; $p <= 60; $p++) {
            $nik = sprintf("317101%02d08%04d", ($p % 28) + 1, $p + 1000);
            $mrn = sprintf("MRN-2026-%05d", $p);
            $patient = Patient::firstOrCreate(
                ['mrn' => $mrn],
                [
                    'name' => "Pasien Demonstrasi {$p}",
                    'nik' => $nik,
                    'birth_date' => CarbonImmutable::now()->subYears(20 + ($p % 50))->format('Y-m-d'),
                    'gender' => ($p % 2 === 0) ? 'FEMALE' : 'MALE',
                    'blood_type' => ['A+', 'B+', 'AB+', 'O+'][$p % 4],
                    'metadata' => [
                        'allergies' => ($p % 5 === 0) ? ['Penicillin'] : [],
                        'insurance_number' => 'BPJS-' . (8800000000 + $p),
                    ],
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
                $occupiedBed = HospitalBed::where('status', 'OCCUPIED')->skip($p - 1)->first();
                if ($occupiedBed) {
                    Admission::firstOrCreate(
                        ['patient_id' => $patient->id, 'status' => 'ADMITTED'],
                        [
                            'bed_id' => $occupiedBed->id,
                            'admission_type' => ($p % 3 === 0) ? 'EMERGENCY' : 'ELECTIVE',
                            'required_class' => $occupiedBed->class,
                            'deposit_amount_idr' => 3000000,
                            'status' => 'ADMITTED',
                            'admission_time' => CarbonImmutable::now()->subDays($p % 7 + 1),
                            'diagnosis_icd10' => ($p % 2 === 0) ? 'A91' : 'I21.9',
                            'metadata' => [
                                'acuity_level' => ($p % 4 === 0) ? 'CRITICAL' : 'STABLE',
                                'clinical_diet' => [
                                    'diet_type' => 'LOW_SALT',
                                    'calories_kcal' => 2000,
                                    'protein_grams' => 60,
                                    'restrictions' => ['NO_EXTRA_SODIUM'],
                                ],
                            ],
                        ]
                    );
                }
            }
        }
    }
}
