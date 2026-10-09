<?php

declare(strict_types=1);

namespace Modules\Edu\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Edu\Domain\Models\EduContingentContract;
use Modules\Edu\Domain\Models\EduHeadhunterContract;
use Modules\Edu\Domain\Models\EduInternalTransfer;
use Modules\Edu\Domain\Models\EduJobOpening;
use Modules\Edu\Domain\Models\EduTalentProfile;
use RuntimeException;

class TalentPipelineAndWorkforceService
{
    public function __construct(
        protected LedgerService $ledgerService
    ) {}

    public function registerTalent(array $params): EduTalentProfile
    {
        return EduTalentProfile::create([
            'id' => (string) Str::uuid(),
            'candidate_id' => $params['candidate_id'],
            'full_name' => $params['full_name'],
            'city' => $params['city'],
            'skills' => $params['skills'],
            'salary_expectation_minor' => (int) $params['salary_expectation_minor'],
            'years_experience' => (int) ($params['years_experience'] ?? 1),
            'status' => 'AVAILABLE',
        ]);
    }

    public function postJob(array $params): EduJobOpening
    {
        return EduJobOpening::create([
            'id' => (string) Str::uuid(),
            'job_code' => $params['job_code'] ?? 'JOB-'.strtoupper(Str::random(6)),
            'title' => $params['title'],
            'hiring_entity_id' => $params['hiring_entity_id'],
            'location_city' => $params['location_city'],
            'required_skills' => $params['required_skills'],
            'salary_budget_minor' => (int) $params['salary_budget_minor'],
            'job_type' => $params['job_type'] ?? 'FULL_TIME',
            'status' => 'OPEN',
        ]);
    }

    /**
     * 136.2 Deterministic matching engine.
     * Test (a): matching deterministik dua run menghasilkan urutan dan skor identik.
     */
    public function matchCandidatesForJob(string $jobCode): array
    {
        $job = EduJobOpening::where('job_code', $jobCode)->firstOrFail();
        $candidates = EduTalentProfile::where('status', 'AVAILABLE')->get();

        $ranked = [];
        foreach ($candidates as $cand) {
            $matchingSkills = array_intersect($job->required_skills, $cand->skills);
            $skillScore = count($job->required_skills) > 0
                ? (count($matchingSkills) / count($job->required_skills)) * 60.0
                : 0.0;

            $locationScore = (strcasecmp($cand->city, $job->location_city) === 0) ? 20.0 : 0.0;

            // Salary expectation match score (up to 20 pts)
            $salaryScore = $cand->salary_expectation_minor <= $job->salary_budget_minor ? 20.0 : 5.0;

            $totalScore = round($skillScore + $locationScore + $salaryScore, 2);

            $ranked[] = [
                'candidate_id' => $cand->candidate_id,
                'full_name' => $cand->full_name,
                'match_score' => $totalScore,
            ];
        }

        // Deterministic sort: match_score DESC, then candidate_id ASC
        usort($ranked, function ($a, $b) {
            if ($b['match_score'] === $a['match_score']) {
                return strcmp($a['candidate_id'], $b['candidate_id']);
            }

            return $b['match_score'] <=> $a['match_score'];
        });

        return $ranked;
    }

    /**
     * 136.3 Headhunter agency fee & warranty period hold.
     * Test (b): agency fee hold sampai garansi lewat.
     */
    public function recordHeadhunterPlacement(array $params): EduHeadhunterContract
    {
        $salary = (int) $params['candidate_first_month_salary_minor'];
        $feePct = (float) ($params['fee_percentage'] ?? 20.0);
        $feeMinor = (int) round(($salary * $feePct) / 100.0);

        $hiredDate = Carbon::parse($params['hired_date']);
        $warrantyDays = (int) ($params['warranty_days'] ?? 90);
        $warrantyEnds = $hiredDate->copy()->addDays($warrantyDays);

        return EduHeadhunterContract::create([
            'id' => (string) Str::uuid(),
            'placement_code' => 'PLC-'.strtoupper(Str::random(8)),
            'headhunter_agency_id' => $params['headhunter_agency_id'],
            'candidate_id' => $params['candidate_id'],
            'hiring_entity_id' => $params['hiring_entity_id'],
            'candidate_first_month_salary_minor' => $salary,
            'fee_percentage' => $feePct,
            'fee_amount_minor' => $feeMinor,
            'warranty_days' => $warrantyDays,
            'hired_date' => $hiredDate->toDateString(),
            'warranty_ends_date' => $warrantyEnds->toDateString(),
            'payout_status' => 'HOLD', // initially on hold
        ]);
    }

    public function releaseHeadhunterPayoutIfWarrantyPassed(string $placementCode, Carbon $currentDate): EduHeadhunterContract
    {
        $contract = EduHeadhunterContract::where('placement_code', $placementCode)->firstOrFail();

        $warrantyEnds = Carbon::parse($contract->warranty_ends_date);

        if ($currentDate->lt($warrantyEnds)) {
            throw new RuntimeException("Warranty period active until {$contract->warranty_ends_date}. Cannot release payout.");
        }

        return DB::transaction(function () use ($contract) {
            $contract->payout_status = 'RELEASED';
            $contract->save();

            // Balanced ledger payout posting (Sum = 0)
            $this->ledgerService->post(new PostingDTO(
                type: 'EDU_HEADHUNTER_FEE_RELEASE',
                description: "Released headhunter fee for placement {$contract->placement_code}",
                idempotencyKey: 'EDU-HH-'.$contract->placement_code,
                entries: [
                    PostingEntryDTO::forCode('edu:headhunter_expense:IDR', 'IDR', $contract->fee_amount_minor),
                    PostingEntryDTO::forCode('edu:headhunter_payable:IDR', 'IDR', -$contract->fee_amount_minor),
                ],
                referenceType: 'HEADHUNTER_CONTRACT',
                referenceId: $contract->placement_code,
            ));

            return $contract;
        });
    }

    /**
     * 136.4 Contingent workforce timesheet.
     * Test (c): contingent timesheet > durasi kontrak ditolak.
     */
    public function createContingentContract(array $params): EduContingentContract
    {
        return EduContingentContract::create([
            'id' => (string) Str::uuid(),
            'contract_code' => $params['contract_code'] ?? 'CTG-'.strtoupper(Str::random(8)),
            'worker_id' => $params['worker_id'],
            'client_entity_id' => $params['client_entity_id'],
            'project_code' => $params['project_code'],
            'contract_max_hours' => (int) $params['contract_max_hours'],
            'hours_rendered' => 0,
            'hourly_rate_minor' => (int) $params['hourly_rate_minor'],
            'valid_until' => $params['valid_until'],
            'status' => 'ACTIVE',
        ]);
    }

    public function logContingentTimesheet(string $contractCode, int $hours): EduContingentContract
    {
        $contract = EduContingentContract::where('contract_code', $contractCode)->firstOrFail();

        if ($contract->hours_rendered + $hours > $contract->contract_max_hours) {
            throw new RuntimeException("Timesheet exceeds maximum contracted hours ({$contract->contract_max_hours}).");
        }

        $contract->hours_rendered += $hours;
        $contract->save();

        return $contract;
    }

    /**
     * 136.5 Internal Mobility & Payroll Consistency.
     * Test (d): transfer antar entitas tak ganda hitung payroll.
     */
    public function processInternalTransfer(array $params): EduInternalTransfer
    {
        return EduInternalTransfer::create([
            'id' => (string) Str::uuid(),
            'transfer_code' => 'XFER-'.strtoupper(Str::random(8)),
            'employee_id' => $params['employee_id'],
            'from_entity_id' => $params['from_entity_id'],
            'to_entity_id' => $params['to_entity_id'],
            'effective_date' => $params['effective_date'],
            'base_salary_minor' => (int) $params['base_salary_minor'],
            'payroll_processed' => false,
            'status' => 'APPROVED',
        ]);
    }

    public function processEntityPayroll(string $transferCode, string $entityId): int
    {
        $xfer = EduInternalTransfer::where('transfer_code', $transferCode)->firstOrFail();

        // If employee has moved away from entityId, entityId should NOT pay
        if ($xfer->from_entity_id === $entityId) {
            return 0; // zero payroll from old entity
        }

        if ($xfer->to_entity_id === $entityId) {
            if ($xfer->payroll_processed) {
                return 0; // idempotent prevention of double payroll
            }
            $xfer->payroll_processed = true;
            $xfer->save();

            return $xfer->base_salary_minor;
        }

        return 0;
    }
}
