<?php

declare(strict_types=1);

namespace Modules\Core\Application\Wave3;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Contracts\Ledger;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class HigherEdCampusAccreditationAndEndowmentGrantAction extends BaseAction
{
    public function __construct(
        protected ?Ledger $ledger = null
    ) {}

    /**
     * Enforces Higher-Ed / K-12 / Vocational academic credit (SKS) caps, accreditation ratios,
     * university endowment corpus perpetuity rules, and tamper-proof diploma hash-chain issuance (Fase 166-167).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params): array {
            $academicCycleCode = (string) ($params['academic_cycle_code'] ?? 'W3-CAMPUS-2026-ODD');
            $studentId = (string) ($params['student_id'] ?? 'STU-UNIV-001');

            // 1. Fase 166: Semester SKS Credit Cap & Graduation GPA Integrity
            $requestedSemesterSks = (int) ($params['requested_semester_sks'] ?? 20);
            $maxAllowedSemesterSks = (int) ($params['max_allowed_semester_sks'] ?? 24);
            if ($requestedSemesterSks > $maxAllowedSemesterSks) {
                throw new DomainException("Academic study plan rejected: requested {$requestedSemesterSks} SKS exceeds maximum allowed {$maxAllowedSemesterSks} SKS");
            }

            $degreeConferralAction = (string) ($params['degree_conferral_action'] ?? 'CONFER_DEGREE');
            $completedTotalSks = (int) ($params['completed_total_sks'] ?? 146);
            $requiredGraduationSks = (int) ($params['required_graduation_sks'] ?? 144);
            $gpaScaled100 = (int) ($params['gpa_scaled_100'] ?? 365); // 3.65
            $minimumGraduationGpaScaled100 = (int) ($params['minimum_graduation_gpa_scaled_100'] ?? 275); // 2.75

            if ($degreeConferralAction === 'CONFER_DEGREE') {
                if ($completedTotalSks < $requiredGraduationSks) {
                    throw new DomainException("Degree conferral blocked: completed {$completedTotalSks} SKS is below required {$requiredGraduationSks} SKS");
                }
                if ($gpaScaled100 < $minimumGraduationGpaScaled100) {
                    throw new DomainException("Degree conferral blocked: GPA {$gpaScaled100} is below minimum graduation GPA {$minimumGraduationGpaScaled100}");
                }
            }

            // 2. Campus Accreditation Faculty-to-Student Ratio Check
            $activeStudentsCount = max(1, (int) ($params['active_students_count'] ?? 600));
            $activeFacultyCount = max(1, (int) ($params['active_faculty_count'] ?? 30));
            $studentsPerFaculty = intdiv($activeStudentsCount, $activeFacultyCount);
            $accreditationTier = $studentsPerFaculty <= 25 ? 'UNGGUL_A' : 'PERLU_PEMBINAAN';

            // 3. University Endowment Perpetuity Guard & Tuition Net Billing
            $endowmentPrincipalCorpusMinor = (int) ($params['endowment_principal_corpus_minor'] ?? 10000000000);
            $annualEndowmentYieldMinor = (int) ($params['annual_endowment_yield_minor'] ?? 600000000);
            $requestedGrantDrawdownMinor = (int) ($params['requested_grant_drawdown_minor'] ?? 450000000);

            if ($requestedGrantDrawdownMinor > $annualEndowmentYieldMinor) {
                throw new DomainException("Endowment perpetuity violation: drawdown ({$requestedGrantDrawdownMinor}) exceeds spendable yield ({$annualEndowmentYieldMinor}) and would erode principal corpus");
            }

            $grossTuitionMinor = (int) ($params['gross_tuition_minor'] ?? 25000000);
            $scholarshipAidMinor = min($grossTuitionMinor, (int) ($params['scholarship_aid_minor'] ?? 5000000));
            $netTuitionBilledMinor = $grossTuitionMinor - $scholarshipAidMinor;

            // 4. Fase 167: W3C Verifiable Academic Credential / Diploma Hash Chain
            $previousDiplomaHash = (string) ($params['previous_diploma_hash'] ?? hash('sha256', 'GENESIS-WAVE3-DIPLOMA'));
            $diplomaCredentialHash = hash('sha256', implode('|', [
                $academicCycleCode,
                $studentId,
                (string) $completedTotalSks,
                (string) $gpaScaled100,
                $accreditationTier,
                $previousDiplomaHash,
            ]));

            if ($netTuitionBilledMinor > 0 && $this->ledger !== null) {
                $idempotencyKey = hash('sha256', "W3:EDU:CAMPUS:{$academicCycleCode}:{$studentId}:{$netTuitionBilledMinor}");
                $this->ledger->post(new PostingDTO(
                    type: 'WAVE3_HIGHER_ED_ENDOWMENT',
                    description: "Higher-Ed campus net tuition & endowment reserve {$academicCycleCode}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('wave3:campus_tuition_receivable:IDR', $netTuitionBilledMinor)
                        ->credit('wave3:campus_endowment_reserve:IDR', $netTuitionBilledMinor)
                        ->toEntries('IDR'),
                    referenceType: 'WAVE3_CAMPUS_ACADEMIC',
                    referenceId: $academicCycleCode,
                ));
            }

            $result = [
                'academic_cycle_code' => $academicCycleCode,
                'student_id' => $studentId,
                'completed_total_sks' => $completedTotalSks,
                'gpa_scaled_100' => $gpaScaled100,
                'accreditation_tier' => $accreditationTier,
                'endowment_principal_corpus_minor' => $endowmentPrincipalCorpusMinor,
                'remaining_spendable_yield_minor' => $annualEndowmentYieldMinor - $requestedGrantDrawdownMinor,
                'net_tuition_billed_minor' => $netTuitionBilledMinor,
                'scholarship_aid_minor' => $scholarshipAidMinor,
                'diploma_credential_hash' => $diplomaCredentialHash,
                'degree_status' => $degreeConferralAction === 'CONFER_DEGREE' ? 'DEGREE_CONFERRED_VERIFIED' : 'ENROLLED_ACTIVE',
            ];

            $this->audit(
                action: 'core.wave3.higher_ed_accreditation_and_endowment_settled',
                newValues: [
                    'academic_cycle_code' => $academicCycleCode,
                    'student_id' => $studentId,
                    'accreditation_tier' => $accreditationTier,
                    'diploma_credential_hash' => $diplomaCredentialHash,
                ],
                correlationId: "w3_edu_{$academicCycleCode}",
                impactType: 'higher_education'
            );

            $this->outbox(
                eventType: 'Wave3HigherEdDiplomaAndEndowmentSettled',
                payload: [
                    'academic_cycle_code' => $academicCycleCode,
                    'student_id' => $studentId,
                    'diploma_credential_hash' => $diplomaCredentialHash,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:W3:EDU:{$academicCycleCode}:{$studentId}")
            );

            return $result;
        });
    }
}
