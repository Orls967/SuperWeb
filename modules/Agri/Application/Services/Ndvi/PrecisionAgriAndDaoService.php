<?php

namespace Modules\Agri\Application\Services\Ndvi;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Agri\Domain\Models\Ndvi\AgriFinancingInstallment;
use Modules\Agri\Domain\Models\Ndvi\AgriSatelliteScan;
use Modules\Agri\Domain\Models\Ndvi\GovProposal;
use Modules\Agri\Domain\Models\Ndvi\GovVote;
use Modules\Agri\Domain\Models\Ndvi\GovVoterWeight;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;

class PrecisionAgriAndDaoService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * 86.1 Record satellite NDVI scan
     */
    public function recordSatelliteScan(
        string $plotCode,
        Carbon $scanDate,
        float $ndviScore,
        ?array $polygonGeojson = null
    ): AgriSatelliteScan {
        $key = hash('sha256', "{$plotCode}:{$scanDate->toDateString()}");

        $health = match (true) {
            $ndviScore >= 0.70 => 'HEALTHY',
            $ndviScore >= 0.50 => 'MILD_STRESS',
            $ndviScore >= 0.30 => 'SEVERE_STRESS',
            default => 'CRITICAL',
        };

        return AgriSatelliteScan::firstOrCreate(
            ['idempotency_key' => $key],
            [
                'scan_code' => 'SCN-'.strtoupper(bin2hex(random_bytes(6))),
                'plot_code' => $plotCode,
                'scan_date' => $scanDate->toDateString(),
                'ndvi_score' => $ndviScore,
                'crop_health_status' => $health,
                'polygon_geojson' => $polygonGeojson,
            ]
        );
    }

    /**
     * 86.2 Conditional loan installment disbursement based on NDVI benchmark
     */
    public function evaluateAndDisburseInstallment(
        AgriFinancingInstallment $installment,
        float $latestNdviScore
    ): AgriFinancingInstallment {
        return DB::transaction(function () use ($installment, $latestNdviScore) {
            $locked = AgriFinancingInstallment::where('id', $installment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'PENDING_EVALUATION') {
                return $locked;
            }

            $locked->evaluated_ndvi = $latestNdviScore;

            if ($latestNdviScore < (float) $locked->required_min_ndvi) {
                // Held: corrective plan required (irrigation & fertilizer support)
                $locked->status = 'HELD_CORRECTIVE_PLAN';
                $locked->corrective_action_plan = 'NDVI below standard. Apply automated drip fertigation and soil moisture replenishment.';
                $locked->save();

                return $locked;
            }

            // NDVI meets benchmark -> disburse capital tranche
            $locked->status = 'DISBURSED';
            $locked->disbursed_at = now();
            $locked->save();

            // Post loan disbursement to ledger: Debit Farmer Microfinance Receivable, Credit Bank Escrow Disbursement
            $this->ledgerService->post(new PostingDTO(
                type: 'AGRI_LOAN_DISBURSEMENT',
                description: "Disburse tranche {$locked->tranche_number} for plot {$locked->plot_code}",
                idempotencyKey: "AGR-DISB-{$locked->installment_code}",
                entries: [
                    PostingEntryDTO::forCode('agri:farmer_loan_ar:IDR', 'IDR', $locked->amount_idr),
                    PostingEntryDTO::forCode('agri:escrow_disbursement:IDR', 'IDR', -$locked->amount_idr),
                ],
                referenceType: 'AGRI_INSTALLMENT',
                referenceId: (string) $locked->id,
            ));

            return $locked;
        });
    }

    /**
     * 86.4 & 86.5 Cast DAO weighted vote with append-only hash chain
     */
    public function castVote(
        GovProposal $proposal,
        GovVoterWeight $voter,
        string $choice // YES, NO, ABSTAIN
    ): GovVote {
        return DB::transaction(function () use ($proposal, $voter, $choice) {
            $lockedProposal = GovProposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            if ($lockedProposal->status !== 'ACTIVE') {
                throw new \RuntimeException('Proposal is no longer active for voting');
            }

            $existingVote = GovVote::where('proposal_id', $lockedProposal->id)
                ->where('voter_user_id', $voter->voter_user_id)
                ->first();

            if ($existingVote) {
                throw new \RuntimeException('Duplicate vote rejected: voter has already cast their ballot');
            }

            $lastVote = GovVote::where('proposal_id', $lockedProposal->id)->latest('id')->first();
            $prevHash = $lastVote ? $lastVote->vote_receipt_hash : str_repeat('0', 64);
            $weight = $voter->voting_weight;

            $receiptHash = hash('sha256', "{$lockedProposal->proposal_code}:{$voter->voter_user_id}:{$choice}:{$weight}:{$prevHash}");

            $vote = GovVote::create([
                'proposal_id' => $lockedProposal->id,
                'voter_user_id' => $voter->voter_user_id,
                'vote_choice' => $choice,
                'weight_cast' => $weight,
                'previous_vote_hash' => $prevHash,
                'vote_receipt_hash' => $receiptHash,
            ]);

            // Update proposal tallies
            if ($choice === 'YES') {
                $lockedProposal->increment('total_yes_weight', $weight);
            } elseif ($choice === 'NO') {
                $lockedProposal->increment('total_no_weight', $weight);
            } else {
                $lockedProposal->increment('total_abstain_weight', $weight);
            }

            return $vote;
        });
    }

    /**
     * 86.6 Finalize proposal & auto-execute project upon quorum approval
     */
    public function finalizeAndExecuteProposal(GovProposal $proposal): GovProposal
    {
        return DB::transaction(function () use ($proposal) {
            $locked = GovProposal::where('id', $proposal->id)->lockForUpdate()->firstOrFail();

            $totalTurnout = $locked->total_yes_weight + $locked->total_no_weight + $locked->total_abstain_weight;
            if ($totalTurnout < $locked->quorum_weight_required) {
                $locked->update(['status' => 'REJECTED']);

                return $locked;
            }

            if ($locked->total_yes_weight > $locked->total_no_weight) {
                $execRef = 'PRJ-AUTO-'.strtoupper(bin2hex(random_bytes(6)));
                $locked->update([
                    'status' => 'APPROVED',
                    'execution_reference_code' => $execRef,
                ]);
            } else {
                $locked->update(['status' => 'REJECTED']);
            }

            return $locked;
        });
    }
}
