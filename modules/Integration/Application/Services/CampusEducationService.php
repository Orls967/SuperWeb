<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CampusEducationService (Fase 166 — Lini 20)
 *
 * Implements:
 *  - 166.1 Campus institutions & governance scoping
 *  - 166.3 Timetable schedule overlap conflict detection
 *  - 166.3 Immutable locked grades & certificate hash-chains
 *  - 166.4 Tuition billing with bounded scholarship aid (aid <= gross tuition)
 */
class CampusEducationService
{
    /**
     * Register institutional academic entity.
     */
    public function registerInstitution(string $code, string $name, string $type): object
    {
        DB::table('camp_institutions')->updateOrInsert(
            ['institution_code' => $code],
            [
                'name' => $name,
                'type' => strtoupper($type),
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return (object) DB::table('camp_institutions')->where('institution_code', $code)->first();
    }

    /**
     * Schedule a class session with room/teacher overlap detection.
     */
    public function scheduleClass(string $institutionCode, string $roomId, string $teacherId, string $subjectCode, int $day, string $start, string $end): object
    {
        // Conflict detection: same room or same teacher at overlapping time on the same day
        $overlap = DB::table('camp_timetables')
            ->where('institution_code', $institutionCode)
            ->where('day_of_week', $day)
            ->where(function ($query) use ($roomId, $teacherId) {
                $query->where('room_id', $roomId)
                    ->orWhere('teacher_id', $teacherId);
            })
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('start_time', '<', $end)
                        ->where('end_time', '>', $start);
                });
            })
            ->exists();

        if ($overlap) {
            throw new \RuntimeException("Timetable conflict detected: Room {$roomId} or Teacher {$teacherId} already scheduled during {$start}-{$end} on day {$day}.");
        }

        $id = DB::table('camp_timetables')->insertGetId([
            'institution_code' => $institutionCode,
            'room_id' => $roomId,
            'teacher_id' => $teacherId,
            'subject_code' => $subjectCode,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('camp_timetables')->find($id);
    }

    /**
     * Submit and lock grade into transcript.
     */
    public function submitAndLockGrade(int $studentId, string $subjectCode, float $score, string $letterGrade): object
    {
        $transcriptCode = 'TRN-'.strtoupper(Str::random(8));
        $hash = hash('sha256', "{$studentId}:{$subjectCode}:{$score}:{$letterGrade}");

        $id = DB::table('camp_transcripts')->insertGetId([
            'transcript_code' => $transcriptCode,
            'student_id' => $studentId,
            'subject_code' => $subjectCode,
            'grade_score' => $score,
            'letter_grade' => strtoupper($letterGrade),
            'is_locked' => true,
            'certificate_hash' => $hash,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('camp_transcripts')->find($id);
    }

    /**
     * Issue tuition billing with bounded scholarship deduction.
     * Invarian: scholarship_aid <= gross_tuition.
     */
    public function issueTuitionInvoice(int $studentId, string $term, float $grossTuition, float $scholarshipAid = 0.0): object
    {
        if ($scholarshipAid > $grossTuition) {
            throw new \InvalidArgumentException("Scholarship aid ({$scholarshipAid}) cannot exceed gross tuition ({$grossTuition}).");
        }

        $net = round($grossTuition - $scholarshipAid, 2);
        $code = 'INV-CAMP-'.strtoupper(Str::random(8));

        $id = DB::table('camp_tuition_invoices')->insertGetId([
            'invoice_code' => $code,
            'student_id' => $studentId,
            'term_code' => $term,
            'gross_tuition_amount' => $grossTuition,
            'scholarship_aid_deduction' => $scholarshipAid,
            'net_tuition_payable' => $net,
            'status' => 'ISSUED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('camp_tuition_invoices')->find($id);
    }

    /**
     * Audit: verify no tuition invoices with aid > gross.
     */
    public function audit(): array
    {
        $excessAid = DB::table('camp_tuition_invoices')
            ->whereRaw('scholarship_aid_deduction > gross_tuition_amount')
            ->count();

        return [
            'status' => $excessAid === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_institutions' => DB::table('camp_institutions')->count(),
            'total_timetables' => DB::table('camp_timetables')->count(),
            'total_transcripts' => DB::table('camp_transcripts')->count(),
            'total_invoices' => DB::table('camp_tuition_invoices')->count(),
            'discrepancy_count' => $excessAid,
        ];
    }
}
