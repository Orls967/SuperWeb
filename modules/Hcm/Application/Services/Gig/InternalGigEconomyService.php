<?php

namespace Modules\Hcm\Application\Services\Gig;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Hcm\Domain\Models\Gig\GovBounty;
use Modules\Hcm\Domain\Models\Gig\GovBountyClaim;
use Modules\Hcm\Domain\Models\Gig\GovBountyPof;

class InternalGigEconomyService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 85.1 Post internal gig bounty
     */
    public function postBounty(
        string $costCenter,
        string $title,
        string $description,
        Carbon $shiftStart,
        Carbon $shiftEnd,
        int $hourlyRateIdr,
        ?string $requiredCertification = null
    ): GovBounty {
        $durationHours = max(1.0, round($shiftStart->diffInMinutes($shiftEnd) / 60.0, 2));

        return GovBounty::create([
            'bounty_code' => 'BNT-'.strtoupper(bin2hex(random_bytes(6))),
            'business_unit_cost_center' => $costCenter,
            'title' => $title,
            'description' => $description,
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'duration_hours' => $durationHours,
            'hourly_rate_idr' => $hourlyRateIdr,
            'required_certification' => $requiredCertification,
            'status' => 'OPEN',
        ]);
    }

    /**
     * 85.2 Match & claim bounty with schedule collision and max daily hours checks (UU 22/2009 <= 8 hrs)
     */
    public function claimBounty(
        GovBounty $bounty,
        int $employeeId,
        array $employeeCertifications = [],
        float $alreadyWorkedHoursToday = 0.0
    ): GovBountyClaim {
        return DB::transaction(function () use ($bounty, $employeeId, $employeeCertifications, $alreadyWorkedHoursToday) {
            $lockedBounty = GovBounty::where('id', $bounty->id)->lockForUpdate()->firstOrFail();

            if ($lockedBounty->status !== 'OPEN') {
                throw new \RuntimeException("Bounty is no longer open ({$lockedBounty->status})");
            }

            // Check certification
            if ($lockedBounty->required_certification && ! in_array($lockedBounty->required_certification, $employeeCertifications)) {
                throw new \RuntimeException("Employee lacks required certification: {$lockedBounty->required_certification}");
            }

            // Check max hours guardrail (UU 22/2009: 8 hours limit / day)
            if (($alreadyWorkedHoursToday + (float) $lockedBounty->duration_hours) > 8.0) {
                throw new \RuntimeException('Claim rejected: exceeds legal daily working hours limit (8 hours)');
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
                throw new \RuntimeException('Claim rejected: employee has a schedule conflict with another active gig shift');
            }

            $lockedBounty->update(['status' => 'CLAIMED']);

            return GovBountyClaim::create([
                'claim_code' => 'CLM-'.strtoupper(bin2hex(random_bytes(6))),
                'bounty_id' => $lockedBounty->id,
                'employee_id' => $employeeId,
                'claimed_at' => now(),
                'status' => 'ASSIGNED',
            ]);
        });
    }

    /**
     * 85.3 Submit supervisor-approved Proof of Work (POF), calculate overtime rate (1.5x), and post payout to Ledger
     */
    public function approveProofOfWorkAndPayout(
        GovBountyClaim $claim,
        float $actualHoursWorked,
        string $geoLocationHash,
        int $supervisorUserId
    ): GovBountyPof {
        return DB::transaction(function () use ($claim, $actualHoursWorked, $geoLocationHash, $supervisorUserId) {
            $lockedClaim = GovBountyClaim::where('id', $claim->id)->lockForUpdate()->firstOrFail();

            if ($lockedClaim->status !== 'ASSIGNED') {
                throw new \RuntimeException("Cannot approve POF for claim in status {$lockedClaim->status}");
            }

            $bounty = $lockedClaim->bounty;
            $overtimeMultiplier = 1.5; // UU rate 1.5x for gig bounty shift
            $payoutIdr = (int) round($actualHoursWorked * $bounty->hourly_rate_idr * $overtimeMultiplier);

            $pof = GovBountyPof::create([
                'pof_code' => 'POF-'.strtoupper(bin2hex(random_bytes(6))),
                'bounty_claim_id' => $lockedClaim->id,
                'actual_hours_worked' => $actualHoursWorked,
                'geo_location_hash' => $geoLocationHash,
                'supervisor_user_id' => $supervisorUserId,
                'calculated_overtime_pay_idr' => $payoutIdr,
                'status' => 'APPROVED',
            ]);

            $lockedClaim->update(['status' => 'PAID']);
            $bounty->update(['status' => 'COMPLETED']);

            // Post payout to ledger:
            // Debit Cost Center Encumbrance Expense (e.g. hcm:gig_cost_center:IDR)
            // Credit Employee Wallet / Bounty Payout (hcm:bounty_payout:IDR)
            $this->ledgerService->post(new PostingDTO(
                type: 'HCM_GIG_BOUNTY_PAYOUT',
                description: "Gig bounty payout for employee {$lockedClaim->employee_id} shift {$bounty->bounty_code}",
                idempotencyKey: "GIG-PAY-{$pof->pof_code}",
                entries: [
                    PostingEntryDTO::forCode('hcm:gig_cost_expense:IDR', 'IDR', $payoutIdr),
                    PostingEntryDTO::forCode('hcm:bounty_payout:IDR', 'IDR', -$payoutIdr),
                ],
                referenceType: 'GIG_BOUNTY_POF',
                referenceId: (string) $pof->id,
            ));

            return $pof;
        });
    }
}
