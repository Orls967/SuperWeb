<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Edu\Application\Services\CampusAcademicService;
use Modules\Edu\Domain\Models\EduCampusClass;
use Modules\Edu\Domain\Models\EduCampusCourse;
use Modules\Edu\Domain\Models\EduCampusInstitution;
use Modules\Edu\Domain\Models\EduCampusTerm;

final class EduCampusT1Seeder extends Seeder
{
    public function run(): void
    {
        $institution = EduCampusInstitution::firstOrCreate(
            ['institution_code' => 'CAMPUS-UNIV-01'],
            [
                'name' => 'Universitas Teknologi SuperWeb',
                'type' => 'UNIVERSITY',
                'is_active' => true,
            ]
        );

        $term = EduCampusTerm::firstOrCreate(
            ['term_code' => 'TERM-2026-GANJIL'],
            [
                'institution_id' => $institution->id,
                'name' => 'Semester Ganjil 2026/2027',
                'academic_year' => '2026/2027',
                'starts_at' => '2026-09-01',
                'ends_at' => '2027-01-31',
                'is_active' => true,
            ]
        );

        $courseAlgo = EduCampusCourse::firstOrCreate(
            ['course_code' => 'CS-101'],
            [
                'institution_id' => $institution->id,
                'name' => 'Algoritma & Pemrograman',
                'credits' => 3,
                'prerequisite_course_code' => null,
                'minimum_gpa_required' => 0.00,
                'is_active' => true,
            ]
        );

        $courseStruct = EduCampusCourse::firstOrCreate(
            ['course_code' => 'CS-201'],
            [
                'institution_id' => $institution->id,
                'name' => 'Struktur Data & Kompleksitas',
                'credits' => 4,
                'prerequisite_course_code' => 'CS-101',
                'minimum_gpa_required' => 2.00,
                'is_active' => true,
            ]
        );

        $courseDb = EduCampusCourse::firstOrCreate(
            ['course_code' => 'CS-202'],
            [
                'institution_id' => $institution->id,
                'name' => 'Basis Data Enterprise',
                'credits' => 3,
                'prerequisite_course_code' => null,
                'minimum_gpa_required' => 0.00,
                'is_active' => true,
            ]
        );

        // Kelas CS-101: Hari Senin 08:00 - 09:40 (480 - 580)
        $classAlgo = EduCampusClass::firstOrCreate(
            ['class_code' => 'CS101-A'],
            [
                'course_id' => $courseAlgo->id,
                'term_id' => $term->id,
                'day_of_week' => 1,
                'start_minute' => 480,
                'end_minute' => 580,
                'room_code' => 'LAB-KOMP-1',
                'lecturer_name' => 'Dr. Budi Santoso',
                'max_capacity' => 35,
                'enrolled_count' => 0,
            ]
        );

        // Kelas CS-202: Hari Senin 10:00 - 11:40 (600 - 700) (TIDAK bentrok)
        $classDb = EduCampusClass::firstOrCreate(
            ['class_code' => 'CS202-A'],
            [
                'course_id' => $courseDb->id,
                'term_id' => $term->id,
                'day_of_week' => 1,
                'start_minute' => 600,
                'end_minute' => 700,
                'room_code' => 'LAB-KOMP-2',
                'lecturer_name' => 'Prof. Maya Indah',
                'max_capacity' => 30,
                'enrolled_count' => 0,
            ]
        );

        // Kelas CS-201: Hari Senin 09:00 - 11:00 (540 - 660) (BENTROK dengan CS-101 dan CS-202)
        EduCampusClass::firstOrCreate(
            ['class_code' => 'CS201-CLASH'],
            [
                'course_id' => $courseStruct->id,
                'term_id' => $term->id,
                'day_of_week' => 1,
                'start_minute' => 540,
                'end_minute' => 660,
                'room_code' => 'LAB-KOMP-3',
                'lecturer_name' => 'Ahmad Fauzi, M.Kom',
                'max_capacity' => 25,
                'enrolled_count' => 0,
            ]
        );

        // Student User
        $student = User::firstOrCreate(
            ['email' => 'student.campus@superweb.test'],
            [
                'name' => 'Rian Hidayat',
                'password' => bcrypt('password'),
                'role' => 'student',
            ]
        );

        $academicService = app(CampusAcademicService::class);

        // Kunci transkrip CS-101 untuk student (Nilai A = 4.00)
        $academicService->lockGradeToTranscript(
            studentUserId: $student->id,
            courseId: $courseAlgo->id,
            termId: $term->id,
            gradePoint: 4.00,
            letterGrade: 'A'
        );

        // Terbitkan tagihan UKT awal
        $academicService->issueTuitionInvoice(
            studentUserId: $student->id,
            termId: $term->id,
            grossTuitionIdr: 7500000,
            scholarshipDeductionIdr: 2500000,
            idempotencyKey: 'seed-ukt-term-2026-ganjil'
        );
    }
}
