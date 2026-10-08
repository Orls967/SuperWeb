<?php

declare(strict_types=1);

namespace Modules\Integration\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ProfessionalServicesService (Fase 175 — Lini 25)
 *
 * Implements:
 *  - 175.2 Procurement marketplace: sealed proposals hidden until formal opening event
 *  - 175.1 Milestone billing: disbursement strictly blocked until deliverable is accepted
 *  - 175.3 Consultant access: least-privilege & time-bound with expiration checks
 */
class ProfessionalServicesService
{
    /**
     * Submit sealed RFP proposal.
     */
    public function submitSealedProposal(string $rfpCode, string $firmId, float $bidAmount): object
    {
        $code = 'PRP-PSV-'.strtoupper(Str::random(8));

        $id = DB::table('psv_proposals')->insertGetId([
            'proposal_code' => $code,
            'rfp_code' => $rfpCode,
            'consulting_firm_id' => $firmId,
            'bid_amount_idr' => $bidAmount,
            'is_sealed' => true,
            'opened_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('psv_proposals')->find($id);
    }

    /**
     * View proposal bids; reveals details only if RFP bids are officially opened.
     */
    public function viewProposalBid(string $proposalCode, bool $userIsProcurementAdmin = false): ?float
    {
        $proposal = DB::table('psv_proposals')->where('proposal_code', $proposalCode)->first();
        if (! $proposal) {
            throw new \InvalidArgumentException("Proposal {$proposalCode} not found.");
        }

        if ((bool) $proposal->is_sealed && ! $userIsProcurementAdmin) {
            // Sealed bids hidden from public/competitors prior to unsealing
            return null;
        }

        return (float) $proposal->bid_amount_idr;
    }

    /**
     * Open sealed proposals.
     */
    public function openProposals(string $rfpCode): int
    {
        return DB::table('psv_proposals')
            ->where('rfp_code', $rfpCode)
            ->update([
                'is_sealed' => false,
                'opened_at' => now(),
                'updated_at' => now(),
            ]);
    }

    /**
     * Create project milestone with deliverable requirement.
     */
    public function createMilestone(string $sowCode, string $title, float $amountIdr): object
    {
        $code = 'MLS-PSV-'.strtoupper(Str::random(8));

        $id = DB::table('psv_milestones')->insertGetId([
            'milestone_code' => $code,
            'sow_code' => $sowCode,
            'title' => $title,
            'milestone_amount_idr' => $amountIdr,
            'is_deliverable_accepted' => false,
            'is_paid' => false,
            'deliverable_checksum' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('psv_milestones')->find($id);
    }

    /**
     * Accept deliverable with document checksum.
     */
    public function acceptDeliverable(string $milestoneCode, string $checksum): object
    {
        DB::table('psv_milestones')->where('milestone_code', $milestoneCode)->update([
            'is_deliverable_accepted' => true,
            'deliverable_checksum' => $checksum,
            'updated_at' => now(),
        ]);

        return (object) DB::table('psv_milestones')->where('milestone_code', $milestoneCode)->first();
    }

    /**
     * Pay milestone disbursement. Enforces gate: milestone cannot be paid before acceptance.
     */
    public function payMilestone(string $milestoneCode): object
    {
        $milestone = DB::table('psv_milestones')->where('milestone_code', $milestoneCode)->first();
        if (! $milestone) {
            throw new \InvalidArgumentException("Milestone {$milestoneCode} not found.");
        }

        if (! (bool) $milestone->is_deliverable_accepted) {
            throw new \RuntimeException("Payment blocked: Deliverable for milestone {$milestoneCode} has not been officially accepted.");
        }

        DB::table('psv_milestones')->where('milestone_code', $milestoneCode)->update([
            'is_paid' => true,
            'updated_at' => now(),
        ]);

        return (object) DB::table('psv_milestones')->where('milestone_code', $milestoneCode)->first();
    }

    /**
     * Grant time-bound consultant access.
     */
    public function grantAccess(int $userId, string $sowCode, Carbon $validUntil): object
    {
        $code = 'ACC-PSV-'.strtoupper(Str::random(8));

        $id = DB::table('psv_consultant_access_grants')->insertGetId([
            'grant_code' => $code,
            'consultant_user_id' => $userId,
            'sow_code' => $sowCode,
            'access_valid_until' => $validUntil->toDateString(),
            'is_revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) DB::table('psv_consultant_access_grants')->find($id);
    }

    /**
     * Check if consultant access is currently active and unexpired.
     */
    public function verifyAccessActive(string $grantCode): bool
    {
        $grant = DB::table('psv_consultant_access_grants')->where('grant_code', $grantCode)->first();
        if (! $grant || (bool) $grant->is_revoked) {
            return false;
        }

        return Carbon::parse($grant->access_valid_until)->isFuture() || Carbon::parse($grant->access_valid_until)->isToday();
    }

    /**
     * Quality audit gate (`psv:audit`).
     */
    public function audit(): array
    {
        $unacceptedPaid = DB::table('psv_milestones')
            ->where('is_paid', true)
            ->where('is_deliverable_accepted', false)
            ->count();

        return [
            'status' => $unacceptedPaid === 0 ? 'HEALTHY' : 'DISCREPANCY_DETECTED',
            'total_proposals' => DB::table('psv_proposals')->count(),
            'total_milestones' => DB::table('psv_milestones')->count(),
            'total_access_grants' => DB::table('psv_consultant_access_grants')->count(),
            'discrepancy_count' => $unacceptedPaid,
        ];
    }
}
