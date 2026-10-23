<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Edu\Domain\Models\EduExamAttempt;
use Modules\Shared\Application\BaseAction;

class EvaluateProctoredExamAttemptAction extends BaseAction
{
    /**
     * @param  array<string, mixed>  $params
     */
    public function execute(array $params): EduExamAttempt
    {
        return DB::transaction(function () use ($params) {
            $studentId = (string) $params['student_party_uuid'];
            $enrollmentCode = (string) $params['enrollment_code'];
            $examTitle = (string) $params['exam_title'];
            $score = (int) $params['score'];
            $passingScore = (int) ($params['passing_score'] ?? 75);
            $proctorSignature = (string) ($params['proctor_signature'] ?? 'PROCTOR_STAMP_DEFAULT');

            $attemptCode = 'EXAM-'.strtoupper(substr(hash('sha256', "{$studentId}:{$enrollmentCode}:{$examTitle}"), 0, 10));
            $proctorHash = hash('sha256', "{$attemptCode}:{$score}:{$passingScore}:{$proctorSignature}");

            $isPassed = $score >= $passingScore;

            $attempt = EduExamAttempt::create([
                'id' => (string) Str::uuid(),
                'attempt_code' => $attemptCode,
                'student_party_uuid' => $studentId,
                'enrollment_code' => $enrollmentCode,
                'exam_title' => $examTitle,
                'score' => $score,
                'passing_score' => $passingScore,
                'is_passed' => $isPassed,
                'proctor_verification_hash' => $proctorHash,
                'status' => 'COMPLETED',
            ]);

            $this->audit(
                action: 'edu_exam_evaluated',
                auditable: $attempt,
                newValues: ['score' => $score, 'is_passed' => $isPassed, 'attempt_code' => $attemptCode],
                impactType: 'examination',
            );

            return $attempt;
        });
    }
}
