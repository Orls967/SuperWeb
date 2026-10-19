<?php

declare(strict_types=1);

namespace Modules\Trade\Application\Services\Bidding;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Banking\Support\Posting;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;
use RuntimeException;

class AiBiddingAgentService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
    }

    /**
     * Create or configure an AI Bidding Agent
     */
    public function createAgent(
        string $agentCode,
        string $entityCode,
        string $commodityCode,
        int $minPriceFloorIdr,
        int $maxPriceCeilingIdr,
        float $targetMarginPct = 15.0,
        float $maxRiskScore = 0.40
    ): BiddingAgent {
        if ($minPriceFloorIdr <= 0 || $maxPriceCeilingIdr <= $minPriceFloorIdr) {
            throw new InvalidArgumentException('Ceiling price must be greater than floor price');
        }

        return BiddingAgent::create([
            'agent_code' => $agentCode,
            'entity_code' => $entityCode,
            'commodity_code' => $commodityCode,
            'min_price_floor_idr' => $minPriceFloorIdr,
            'max_price_ceiling_idr' => $maxPriceCeilingIdr,
            'target_margin_pct' => $targetMarginPct,
            'max_risk_score' => $maxRiskScore,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * 84.2 Deterministic AI evaluation run for tender auctions
     */
    public function runEvaluation(
        BiddingAgent $agent,
        string $tenderCode,
        int $commodityFeedPriceIdr,
        int $logisticsCostIdr,
        float $buyerRiskScore
    ): BidRun {
        if ($commodityFeedPriceIdr <= 0 || $logisticsCostIdr < 0) {
            throw new InvalidArgumentException('Commodity and logistics costs must be positive');
        }

        if ($buyerRiskScore > (float) $agent->max_risk_score) {
            throw new RuntimeException("Buyer risk score ({$buyerRiskScore}) exceeds agent safety threshold ({$agent->max_risk_score})");
        }

        // Deterministic price calculation: cost = commodity + logistics
        $totalCost = $commodityFeedPriceIdr + $logisticsCostIdr;
        $targetMarginMultiplier = 1.0 + ((float) $agent->target_margin_pct / 100.0);
        $rawBid = (int) round($totalCost * $targetMarginMultiplier);

        // Clamp to agent configured boundaries
        if ($rawBid < $agent->min_price_floor_idr || $rawBid > $agent->max_price_ceiling_idr) {
            throw new RuntimeException("Optimal bid price ({$rawBid}) falls outside agent allowed boundaries [{$agent->min_price_floor_idr} - {$agent->max_price_ceiling_idr}]");
        }

        // 84.3 Standard contract commercial clause drafts
        $clauses = [
            'payment_terms' => 'NET_30_LC',
            'delivery_incoterm' => 'FOB_JAKARTA',
            'penalty_rate_per_day' => '0.001',
            'force_majeure' => 'ICC_FORCE_MAJEURE_2020',
        ];

        // Deterministic run code generation (A4 compliant)
        $entropy = hash('sha256', "{$agent->agent_code}:{$tenderCode}:{$commodityFeedPriceIdr}:{$logisticsCostIdr}");
        $runCode = 'RUN-'.strtoupper(substr($entropy, 0, 12));
        $evalHash = hash('sha256', "{$agent->agent_code}:{$tenderCode}:{$commodityFeedPriceIdr}:{$logisticsCostIdr}:{$rawBid}:".json_encode($clauses));

        return BidRun::updateOrCreate(
            ['run_code' => $runCode],
            [
                'bidding_agent_id' => $agent->id,
                'tender_reference_code' => $tenderCode,
                'commodity_feed_price_idr' => $commodityFeedPriceIdr,
                'estimated_logistics_cost_idr' => $logisticsCostIdr,
                'buyer_risk_score' => $buyerRiskScore,
                'optimal_bid_price_idr' => $rawBid,
                'clause_drafts' => $clauses,
                'evaluation_hash' => $evalHash,
            ]
        );
    }

    /**
     * Create draft submission requiring four-eyes approval
     */
    public function prepareSubmission(BidRun $run): BidSubmission
    {
        // Deterministic code generation (A4 compliant)
        $subEntropy = hash('sha256', "{$run->run_code}:SUB:{$run->id}");
        $submissionCode = 'SUB-'.strtoupper(substr($subEntropy, 0, 12));

        return BidSubmission::create([
            'submission_code' => $submissionCode,
            'bid_run_id' => $run->id,
            'submitted_bid_price_idr' => $run->optimal_bid_price_idr,
            'requires_four_eyes' => true,
            'status' => 'PENDING_APPROVAL',
        ]);
    }

    /**
     * 84.4 Four-eyes mandatory approval before submission
     */
    public function approveAndSubmit(BidSubmission $submission, int $approverUserId): BidSubmission
    {
        return DB::transaction(function () use ($submission, $approverUserId) {
            $locked = BidSubmission::where('id', $submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'PENDING_APPROVAL') {
                throw new RuntimeException("Cannot approve submission in status {$locked->status}");
            }

            $locked->update([
                'approved_by_user_id' => $approverUserId,
                'approved_at' => now(),
                'status' => 'SUBMITTED',
            ]);

            return $locked;
        });
    }

    /**
     * 84.5 Handle post-win tender: issue contract code & encumber budget commitment
     */
    public function recordWonAuction(BidSubmission $submission): BidSubmission
    {
        return DB::transaction(function () use ($submission) {
            $locked = BidSubmission::where('id', $submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'SUBMITTED') {
                throw new RuntimeException('Only submitted bids can be marked as WON');
            }

            // Deterministic contract code (A4 compliant)
            $ctrEntropy = hash('sha256', "{$locked->submission_code}:CTR:{$locked->id}");
            $contractCode = 'CTR-TND-'.strtoupper(substr($ctrEntropy, 0, 12));

            $locked->update([
                'status' => 'WON',
                'created_contract_code' => $contractCode,
            ]);

            $totalValue = $locked->submitted_bid_price_idr;

            // Commit contract budget in ledger: Debit Tender Contract Asset, Credit Deferred Revenue
            $this->ledgerService->post(new PostingDTO(
                type: 'TENDER_CONTRACT_AWARD',
                description: "Tender award budget commitment for contract {$contractCode}",
                idempotencyKey: "TRD-TND-{$contractCode}",
                entries: Posting::lines()
                    ->debit('trd:tender_contract_ar:IDR', $totalValue)
                    ->credit('trd:tender_revenue_unearned:IDR', $totalValue)
                    ->toEntries('IDR'),
                referenceType: 'TENDER_CONTRACT',
                referenceId: $contractCode,
            ));

            return $locked;
        });
    }

    /**
     * 84.5 Post-loss feedback logging
     */
    public function recordLostAuction(BidSubmission $submission, string $feedbackReason): BidSubmission
    {
        return DB::transaction(function () use ($submission, $feedbackReason) {
            $locked = BidSubmission::where('id', $submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'SUBMITTED') {
                throw new RuntimeException('Only submitted bids can be marked as LOST');
            }

            $locked->update([
                'status' => 'LOST',
                'feedback_reason' => $feedbackReason,
            ]);

            return $locked;
        });
    }

    /**
     * 84.6 Dashboard bid desk summary
     */
    public function getBidDeskSummary(): array
    {
        $agentsCount = BiddingAgent::count();
        $runsCount = BidRun::count();
        $submissions = BidSubmission::all();

        $pending = $submissions->where('status', 'PENDING_APPROVAL')->count();
        $submitted = $submissions->where('status', 'SUBMITTED')->count();
        $won = $submissions->where('status', 'WON')->count();
        $lost = $submissions->where('status', 'LOST')->count();

        $decidedTotal = $won + $lost;
        $winRate = $decidedTotal > 0 ? round(($won / $decidedTotal) * 100, 1) : 0.0;
        $wonValueIdr = (int) $submissions->where('status', 'WON')->sum('submitted_bid_price_idr');

        return [
            'total_agents' => $agentsCount,
            'total_evaluations' => $runsCount,
            'pending_approval_count' => $pending,
            'submitted_count' => $submitted,
            'won_count' => $won,
            'lost_count' => $lost,
            'win_rate_percent' => $winRate,
            'total_won_value_idr' => $wonValueIdr,
        ];
    }
}
