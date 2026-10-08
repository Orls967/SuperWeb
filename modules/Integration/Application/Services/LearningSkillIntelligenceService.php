<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LearningSkillIntelligenceService (Fase 227)
 *
 * Implements:
 *  - 227.1 Learning cloud catalog across 30 lines & learning hour tracking
 *  - 227.2 Skill ontology, unit gap analysis & mandatory role certification gates
 *  - 227.3 Content versioning, pre/post evaluation & content factory lifecycle
 *  - 227.6 Edge case: Expired mandatory certification safely flags assignment hold for new operational duties
 *  - 227.7 Content version lock per enrollment (immutable content during in-flight classes)
 */
class LearningSkillIntelligenceService
{
    /**
     * Publish or update a course version in learning catalog (227.1 & 227.3).
     */
    public function publishCourse(
        string $courseCode,
        string $version,
        string $title,
        string $type,
        float $learningHours = 1.0
    ): object {
        DB::table('edu_learning_courses')->updateOrInsert(
            [
                'course_code' => strtoupper($courseCode),
                'version' => $version,
            ],
            [
                'title' => $title,
                'type' => strtoupper($type),
                'learning_hours' => $learningHours,
                'status' => 'ACTIVE',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return (object) DB::table('edu_learning_courses')
            ->where('course_code', strtoupper($courseCode))
            ->where('version', $version)
            ->first();
    }

    /**
     * Retire older course version.
     */
    public function retireCourseVersion(string $courseCode, string $version): void
    {
        DB::table('edu_learning_courses')
            ->where('course_code', strtoupper($courseCode))
            ->where('version', $version)
            ->update([
                'status' => 'RETIRED',
                'updated_at' => now(),
            ]);
    }

    /**
     * Enroll employee with immutable version lock (227.7 Edge Case).
     */
    public function enrollEmployee(string $employeeId, string $courseCode): object
    {
        // Find latest active version
        $activeCourse = DB::table('edu_learning_courses')
            ->where('course_code', strtoupper($courseCode))
            ->where('status', 'ACTIVE')
            ->orderBy('id', 'desc')
            ->first();

        if (! $activeCourse) {
            throw new \InvalidArgumentException("No active course version found for {$courseCode}.");
        }

        $code = 'ENR-'.strtoupper(Str::random(8));

        $id = DB::table('edu_course_enrollments')->insertGetId([
            'enrollment_code' => $code,
            'employee_id' => strtoupper($employeeId),
            'course_code' => strtoupper($courseCode),
            'locked_version' => $activeCourse->version, // Immutable version lock (227.7)
            'progress_pct' => 0,
            'pre_test_score' => null,
            'post_test_score' => null,
            'status' => 'ENROLLED',
            'completed_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('edu_course_enrollments')->find($id);
    }

    /**
     * Complete course with effectiveness assessment (pre/post test delta) (227.3 & 227.5).
     */
    public function completeCourse(string $enrollmentCode, float $preTestScore, float $postTestScore): object
    {
        $enrollment = DB::table('edu_course_enrollments')
            ->where('enrollment_code', strtoupper($enrollmentCode))
            ->first();

        if (! $enrollment) {
            throw new \InvalidArgumentException("Enrollment {$enrollmentCode} not found.");
        }

        DB::table('edu_course_enrollments')
            ->where('enrollment_code', strtoupper($enrollmentCode))
            ->update([
                'progress_pct' => 100.0,
                'pre_test_score' => $preTestScore,
                'post_test_score' => $postTestScore,
                'status' => 'COMPLETED',
                'completed_at' => now(),
                'updated_at' => now(),
            ]);

        return (object) DB::table('edu_course_enrollments')->where('enrollment_code', strtoupper($enrollmentCode))->first();
    }

    /**
     * Issue certification to employee.
     */
    public function issueCertification(
        string $employeeId,
        string $skillCode,
        string $expiresAt,
        bool $isMandatory = false
    ): object {
        $certCode = 'CERT-'.strtoupper(Str::random(8));

        $id = DB::table('edu_employee_certifications')->insertGetId([
            'certification_code' => $certCode,
            'employee_id' => strtoupper($employeeId),
            'skill_code' => strtoupper($skillCode),
            'is_mandatory_for_role' => $isMandatory,
            'expires_at' => $expiresAt,
            'status' => 'ACTIVE',
            'assignment_hold' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('edu_employee_certifications')->find($id);
    }

    /**
     * Check compliance role gate & handle expired certifications (227.6 Edge Case).
     */
    public function verifyRoleCertificationGate(string $employeeId, string $skillCode): bool
    {
        $cert = DB::table('edu_employee_certifications')
            ->where('employee_id', strtoupper($employeeId))
            ->where('skill_code', strtoupper($skillCode))
            ->orderBy('expires_at', 'desc')
            ->first();

        if (! $cert) {
            return false;
        }

        $today = now()->format('Y-m-d');

        // Edge Case 227.6: sertifikasi wajib kedaluwarsa saat tugas berjalan -> handover aman + tugas baru tertahan
        if ($cert->expires_at < $today) {
            DB::table('edu_employee_certifications')
                ->where('id', $cert->id)
                ->update([
                    'status' => 'EXPIRED',
                    'assignment_hold' => $cert->is_mandatory_for_role,
                    'updated_at' => now(),
                ]);

            return false;
        }

        return $cert->status === 'ACTIVE';
    }

    /**
     * Assess unit skill gaps (227.2 & 227.5).
     */
    public function assessSkillGap(
        string $unitCode,
        string $skillCode,
        float $requiredProficiency,
        float $averageProficiency
    ): object {
        $gap = max(0.0, round($requiredProficiency - $averageProficiency, 2));

        $id = DB::table('edu_skill_gap_assessments')->insertGetId([
            'unit_code' => strtoupper($unitCode),
            'skill_code' => strtoupper($skillCode),
            'required_proficiency' => $requiredProficiency,
            'average_proficiency' => $averageProficiency,
            'gap_score' => $gap,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('edu_skill_gap_assessments')->find($id);
    }

    /**
     * Quality audit gate (`campus:audit` & `edu:audit`).
     */
    public function audit(): array
    {
        $today = now()->format('Y-m-d');

        // Discrepancy 1: Mandatory expired certification without assignment hold flagged
        $unheldExpiredCertifications = DB::table('edu_employee_certifications')
            ->where('is_mandatory_for_role', true)
            ->where('expires_at', '<', $today)
            ->where('assignment_hold', false)
            ->count();

        // Discrepancy 2: Enrollments missing locked version
        $unlockedEnrollments = DB::table('edu_course_enrollments')
            ->whereNull('locked_version')
            ->count();

        $discrepancies = $unheldExpiredCertifications + $unlockedEnrollments;

        return [
            'status' => $discrepancies === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_courses' => DB::table('edu_learning_courses')->count(),
            'total_enrollments' => DB::table('edu_course_enrollments')->count(),
            'total_certifications' => DB::table('edu_employee_certifications')->count(),
            'discrepancy_count' => $discrepancies,
        ];
    }
}
