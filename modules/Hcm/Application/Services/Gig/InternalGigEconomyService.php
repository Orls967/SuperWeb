<?php

declare(strict_types=1);

namespace Modules\Hcm\Application\Services\Gig;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hcm\Domain\Models\Gig\GovBountyPof;
use Modules\Hcm\Ledger\HcmTransactionTypes;
use RuntimeException;

class InternalGigEconomyService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 85.1 Post internal gig bounty with embedded insurance and speed incentives
     */
    public function postBounty(
        string $costCenter,
        string $title,
        string $description,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        int $hourlyRateIdr,
        ?string $requiredCertification = null,
        bool $requiresHighRiskPermit = false,
        int $speedBonusIdr = 0
    ): GovBounty {
        $durationHours = max(1.0, round($shiftStart->diffInMinutes($shiftEnd) / 60.0, 2));

        // Deterministic bounty code & insurance policy ref (Rule A4 compliant)
        $hashEntropy = hash('sha256', "{$costCenter}:{$title}:{$shiftStart->toIso8601String()}:{$hourlyRateIdr}");
        $bountyCode = 'BNT-'.strtoupper(substr($hashEntropy, 0, 12));
        $insuranceRef = 'INS-GIG-'.strtoupper(substr(hash('sha256', "INS:{$bountyCode}"), 0, 10));

        return GovBounty::create([
            'bounty_code' => $bountyCode,
            'business_unit_cost_center' => $costCenter,
            'title' => $title,
            'description' => $description,
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'duration_hours' => $durationHours,
            'hourly_rate_idr' => $hourlyRateIdr,
            'required_certification' => $requiredCertification,
            'requires_high_risk_permit' => $requiresHighRiskPermit,
            'embedded_insurance_policy_ref' => $insuranceRef,
            'speed_bonus_idr' => $speedBonusIdr,
            'unit_rating_score' => 4.85,
            'status' => 'OPEN',
        ]);
    }

    /**
     * 85.2 Match & claim bounty with schedule collision, high-risk permit, and legal work hour guardrails (UU <= 8 hrs/day, <= 14 hrs overtime/wk)
     */
    public function claimBounty(
        GovBounty $bounty,
        int $employeeId,
        array $employeeCertifications = [],
        float $alreadyWorkedHoursToday = 0.0,
        float $alreadyWorkedOvertimeThisWeek = 0.0,
        ?string $workPermitCode = null
    ): GovBountyClaim {
        return DB::transaction(function () use (
            $bounty,
            $employeeId,
            $employeeCertifications,
            $alreadyWorkedHoursToday,
            $alreadyWorkedOvertimeThisWeek,
            $workPermitCode
        ) {
            $lockedBounty = GovBounty::where('id', $bounty->id)->lockForUpdate()->firstOrFail();

            if ($lockedBounty->status !== 'OPEN') {
                throw new RuntimeException("Bounty is no longer open ({$lockedBounty->status})");
            }

            // Check certification
            if ($lockedBounty->required_certification && ! in_array($lockedBounty->required_certification, $employeeCertifications, true)) {
                throw new RuntimeException("Employee lacks required certification: {$lockedBounty->required_certification}");
            }

            // 85.4 Safety: High-risk work permit check (Fase 40.6)
            if ($lockedBounty->requires_high_risk_permit && (empty($workPermitCode) || trim($workPermitCode) === '')) {
                throw new RuntimeException('Claim rejected: High-risk work permit is mandatory for this dangerous gig task');
            }

            // Check max daily hours guardrail (UU 22/2009: 8.0 hours limit / day)
            $totalToday = $alreadyWorkedHoursToday + (float) $lockedBounty->duration_hours;
            if ($totalToday > 8.0) {
                throw new RuntimeException("Claim rejected: exceeds legal daily working hours limit (8 hours). Requested total: {$totalToday} hrs");
            }

            // 85.4 Weekly overtime limit guardrail (UU 22/2009: max 14 hours overtime/week)
            $totalWeeklyOvertime = $alreadyWorkedOvertimeThisWeek + (float) $lockedBounty->duration_hours;
            if ($totalWeeklyOvertime > 14.0) {
                throw new RuntimeException("Claim rejected: exceeds weekly overtime limit (14 hours). Requested total: {$totalWeeklyOvertime} hrs");
            }

            // Check schedule collision with another active claim of this employee
            $hasCollision = GovBountyClaim::where('employee_id', $employeeId)
                ->whereIn('status', ['ASSIGNED', 'WORKED'])
                ->whereHas('bounty', function ($q) use ($lockedBounty) {
                    $q->where(function ($sub) use ($lockedBounty) {
                        $sub->whereBetween('shift_start', [$lockedBounty->shift_start, $lockedBounty->shift_end])
                            ->orWhereBetween('shift_end', [$lockedBounty->shift_start, $lockedBounty->shift_end]);
                    });
                })
                ->exists();

            if ($hasCollision) {
                throw new RuntimeException('Claim rejected: employee has a schedule conflict with another active gig shift');
            }

            $lockedBounty->update(['status' => 'CLAIMED']);

            // Deterministic claim code (Rule A4 compliant)
            $claimCode = 'CLM-'.strtoupper(substr(hash('sha256', "{$lockedBounty->id}:{$employeeId}"), 0, 12));

            return GovBountyClaim::create([
                'claim_code' => $claimCode,
                'bounty_id' => $lockedBounty->id,
                'employee_id' => $employeeId,
                'work_permit_code' => $workPermitCode,
                'mobility_points_earned' => 10,
                'claimed_at' => now(),
                'status' => 'ASSIGNED',
            ]);
        });
    }

    /**
     * 85.3 Submit supervisor-approved Proof of Work (POF), calculate overtime rate (1.5x + speed bonus), and post payout to Ledger
     */
    public function approveProofOfWorkAndPayout(
        GovBountyClaim $claim,
        float $actualHoursWorked,
        string $geoLocationHash,
        int $supervisorUserId,
        ?string $photoEvidenceRef = null
    ): GovBountyPof {
        return DB::transaction(function () use (
            $claim,
            $actualHoursWorked,
            $geoLocationHash,
            $supervisorUserId,
            $photoEvidenceRef
        ) {
            $lockedClaim = GovBountyClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if ($lockedClaim->status !== 'ASSIGNED') {
                throw new RuntimeException("Cannot approve POF for claim in status {$lockedClaim->status}");
            }

            // Conflict of interest guardrail: supervisor cannot approve own gig work
            if ($supervisorUserId === (int) $lockedClaim->employee_id) {
                throw new RuntimeException('Supervisor cannot approve their own gig proof of work');
            }

            $bounty = $lockedClaim->bounty;
            $overtimeMultiplier = 1.5; // UU rate 1.5x for gig bounty shift
            $baseOvertimePay = intdiv((int) round($actualHoursWorked * 100) * $bounty->hourly_rate_idr * 15 + 500, 1000);
            $totalPayoutIdr = $baseOvertimePay + (int) ($bounty->speed_bonus_idr ?? 0);

            // Deterministic POF code (Rule A4 compliant)
            $pofCode = 'POF-'.strtoupper(substr(hash('sha256', "{$lockedClaim->id}:{$actualHoursWorked}:{$supervisorUserId}"), 0, 12));

            $pof = GovBountyPof::create([
                'pof_code' => $pofCode,
                'bounty_claim_id' => $lockedClaim->id,
                'actual_hours_worked' => $actualHoursWorked,
                'overtime_multiplier' => $overtimeMultiplier,
                'geo_location_hash' => $geoLocationHash,
                'photo_evidence_ref' => $photoEvidenceRef ?? "EVD-{$pofCode}.JPG",
                'supervisor_user_id' => $supervisorUserId,
                'calculated_overtime_pay_idr' => $totalPayoutIdr,
                'status' => 'APPROVED',
            ]);

            $lockedClaim->update(['status' => 'PAID']);
            $bounty->update(['status' => 'COMPLETED']);

            // Post payout to ledger:
            // Debit Cost Center Encumbrance Expense (hcm:gig_cost_expense:IDR)
            // Credit Employee Wallet / Bounty Payout (hcm:bounty_payout:IDR)
            $this->ledgerService->post(new PostingDTO(
                type: HcmTransactionTypes::HCM_GIG_BOUNTY_PAYOUT->value,
                description: "Gig bounty payout for employee {$lockedClaim->employee_id} shift {$bounty->bounty_code}",
                idempotencyKey: "GIG-PAY-{$pof->pof_code}",
                entries: [
                    PostingEntryDTO::forCode('hcm:gig_cost_expense:IDR', 'IDR', $totalPayoutIdr),
                    PostingEntryDTO::forCode('hcm:bounty_payout:IDR', 'IDR', -$totalPayoutIdr),
                ],
                referenceType: 'GIG_BOUNTY_POF',
                referenceId: (string) $pof->id,
            ));

            return $pof;
        });
    }

    /**
     * 85.6 Dashboard marketplace summary
     */
    public function getBountyMarketplaceSummary(): array
    {
        $bounties = GovBounty::all();
        $claims = GovBountyClaim::all();
        $pofs = GovBountyPof::all();

        $openCount = $bounties->where('status', 'OPEN')->count();
        $claimedCount = $bounties->where('status', 'CLAIMED')->count();
        $completedCount = $bounties->where('status', 'COMPLETED')->count();

        $totalPayoutIdr = (int) $pofs->sum('calculated_overtime_pay_idr');
        $totalMobilityPoints = (int) $claims->sum('mobility_points_earned');
        $avgRating = $bounties->count() > 0 ? round((float) $bounties->avg('unit_rating_score'), 2) : 5.00;

        return [
            'total_bounties' => $bounties->count(),
            'open_bounties' => $openCount,
            'claimed_bounties' => $claimedCount,
            'completed_bounties' => $completedCount,
            'total_payout_idr' => $totalPayoutIdr,
            'total_mobility_points' => $totalMobilityPoints,
            'average_unit_rating' => $avgRating,
        ];
    }
}
