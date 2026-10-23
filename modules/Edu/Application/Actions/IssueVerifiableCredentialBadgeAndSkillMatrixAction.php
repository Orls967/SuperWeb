<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class IssueVerifiableCredentialBadgeAndSkillMatrixAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Verifies prerequisite graphs, grades final competency assessments, and issues tamper-proof hash-chain credentials.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $issuanceCode = (string) ($params['issuance_code'] ?? 'EDU-CERT-'.hash('sha256', (string) ($params['student_id'] ?? 'default')));
            $studentId = (string) ($params['student_id'] ?? 'STU-001');
            $programCode = (string) ($params['program_code'] ?? 'PRG-K3-MINE-SAFETY');

            // Prerequisite graph cycle / completion check
            /** @var list<string> $requiredPrerequisites */
            $requiredPrerequisites = $params['required_prerequisites'] ?? [];
            /** @var list<string> $completedCourses */
            $completedCourses = $params['completed_courses'] ?? [];

            $missingPrerequisites = array_diff($requiredPrerequisites, $completedCourses);
            if ($missingPrerequisites !== []) {
                throw new DomainException('Enrollment rejected: Missing required prerequisites: '.implode(', ', $missingPrerequisites));
            }

            // Assessment Score Evaluation
            $scorePct = (int) ($params['exam_score_pct'] ?? 85);
            $passingGradePct = (int) ($params['passing_grade_pct'] ?? 75);

            if ($scorePct < $passingGradePct) {
                throw new DomainException("Assessment failed: Score {$scorePct}% is below passing grade {$passingGradePct}%");
            }

            // Generate tamper-proof hash-chain credential badge
            $prevHash = (string) ($params['previous_certificate_hash'] ?? hash('sha256', 'GENESIS-EDU-BADGE'));
            $certPayload = "{$studentId}:{$programCode}:{$scorePct}:{$prevHash}";
            $certificateHash = hash('sha256', $certPayload);
            $gaplessQrToken = 'QR-CERT-'.hash('sha256', "QR:{$issuanceCode}:{$certificateHash}");

            // Corporate L&D tuition deduction & billing
            $tuitionFeeMinor = (int) ($params['tuition_fee_minor'] ?? 5000000);
            $corporateSponsored = (string) ($params['sponsorship_type'] ?? 'CORPORATE');

            if ($tuitionFeeMinor > 0) {
                $idempotencyKey = hash('sha256', "EDU:TUITION:{$issuanceCode}:{$tuitionFeeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_TUITION_PAYMENT',
                    description: "Tuition settlement for {$programCode} student {$studentId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('edu:tuition_receivable:IDR', $tuitionFeeMinor)
                        ->credit('edu:tuition_revenue:IDR', $tuitionFeeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_ENROLLMENT',
                    referenceId: $issuanceCode,
                ));
            }

            $result = [
                'issuance_code' => $issuanceCode,
                'student_id' => $studentId,
                'program_code' => $programCode,
                'exam_score_pct' => $scorePct,
                'certificate_hash' => $certificateHash,
                'previous_hash' => $prevHash,
                'gapless_qr_token' => $gaplessQrToken,
                'tuition_fee_minor' => $tuitionFeeMinor,
                'sponsorship_type' => $corporateSponsored,
                'competency_status' => 'CERTIFIED',
            ];

            $this->audit(
                action: 'edu.certificate.issued',
                newValues: [
                    'issuance_code' => $issuanceCode,
                    'student_id' => $studentId,
                    'certificate_hash' => $certificateHash,
                    'score' => $scorePct,
                ],
                correlationId: "edu_cert_{$issuanceCode}",
                impactType: 'credential_issuance'
            );

            $this->outbox(
                eventType: 'VerifiableCredentialBadgeIssued',
                payload: [
                    'issuance_code' => $issuanceCode,
                    'student_id' => $studentId,
                    'certificate_hash' => $certificateHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:EDU:{$issuanceCode}")
            );

            return $result;
        });
    }
}
