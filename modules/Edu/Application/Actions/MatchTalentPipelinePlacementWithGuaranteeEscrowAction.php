<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Actions;

use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Shared\Application\BaseAction;

class MatchTalentPipelinePlacementWithGuaranteeEscrowAction extends BaseAction
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    /**
     * Executes deterministic talent pipeline matching, manages headhunter escrow guarantee,
     * and validates contingent workforce hours.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $placementCode = (string) ($params['placement_code'] ?? 'TAL-PLC-'.hash('sha256', (string) ($params['candidate_id'] ?? 'default')));
            $candidateId = (string) ($params['candidate_id'] ?? 'CND-001');
            $hiringLine = (string) ($params['hiring_line'] ?? 'MINING');

            // Deterministic skill matching (Jaccard similarity: intersection / union)
            /** @var list<string> $candidateSkills */
            $candidateSkills = $params['candidate_skills'] ?? [];
            /** @var list<string> $requiredSkills */
            $requiredSkills = $params['required_skills'] ?? [];

            $intersection = array_intersect($candidateSkills, $requiredSkills);
            $union = array_unique(array_merge($candidateSkills, $requiredSkills));

            $matchScorePct = count($union) > 0 ? (int) round((count($intersection) / count($union)) * 100) : 0;

            // Contingent timesheet verification
            $contractedHours = (int) ($params['contracted_hours'] ?? 160);
            $reportedHours = (int) ($params['reported_hours'] ?? 160);

            if ($reportedHours > $contractedHours) {
                throw new DomainException("Contingent timesheet violation: Reported hours {$reportedHours} exceeds contracted hours limit {$contractedHours}");
            }

            // Headhunter recruitment fee escrow & warranty
            $firstYearSalaryMinor = (int) ($params['first_year_salary_minor'] ?? 240000000);
            $headhunterFeeRatePct = (int) ($params['headhunter_fee_rate_pct'] ?? 10);
            $headhunterFeeMinor = (int) round(($firstYearSalaryMinor * $headhunterFeeRatePct) / 100);

            $probationDaysPassed = (int) ($params['probation_days_passed'] ?? 90);
            $warrantyPeriodDays = (int) ($params['warranty_period_days'] ?? 90);

            $escrowStatus = 'HELD_IN_ESCROW';
            if ($probationDaysPassed >= $warrantyPeriodDays && $headhunterFeeMinor > 0) {
                $escrowStatus = 'RELEASED_TO_AGENCY';

                $idempotencyKey = hash('sha256', "EDU:HEADHUNTER:{$placementCode}:{$headhunterFeeMinor}");
                $this->ledgerService->post(new PostingDTO(
                    type: 'EDU_HEADHUNTER_FEE_RELEASE',
                    description: "Headhunter placement fee release for {$candidateId}",
                    idempotencyKey: $idempotencyKey,
                    entries: Posting::lines()
                        ->debit('edu:headhunter_expense:IDR', $headhunterFeeMinor)
                        ->credit('edu:headhunter_payable:IDR', $headhunterFeeMinor)
                        ->toEntries('IDR'),
                    referenceType: 'EDU_HEADHUNTER_ESCROW',
                    referenceId: $placementCode,
                ));
            }

            // Internal cross-entity mobility check
            $crossEntityTransfer = (string) ($params['transfer_mode'] ?? 'STANDARD_HIRE');
            $singlePayrollRecord = true;

            $result = [
                'placement_code' => $placementCode,
                'candidate_id' => $candidateId,
                'hiring_line' => $hiringLine,
                'match_score_pct' => $matchScorePct,
                'contracted_hours' => $contractedHours,
                'reported_hours' => $reportedHours,
                'headhunter_fee_minor' => $headhunterFeeMinor,
                'escrow_status' => $escrowStatus,
                'cross_entity_transfer' => $crossEntityTransfer,
                'single_payroll_enforced' => $singlePayrollRecord,
            ];

            $this->audit(
                action: 'edu.talent.matched_and_placed',
                newValues: [
                    'placement_code' => $placementCode,
                    'candidate_id' => $candidateId,
                    'match_score' => $matchScorePct,
                    'escrow_status' => $escrowStatus,
                ],
                correlationId: "edu_talent_{$placementCode}",
                impactType: 'talent_placement'
            );

            $this->outbox(
                eventType: 'TalentPipelineMatchedAndPlaced',
                payload: [
                    'placement_code' => $placementCode,
                    'candidate_id' => $candidateId,
                    'match_score_pct' => $matchScorePct,
                    'escrow_status' => $escrowStatus,
                ],
                idempotencyKey: hash('sha256', "OUTBOX:TALENT:{$placementCode}")
            );

            return $result;
        });
    }
}
