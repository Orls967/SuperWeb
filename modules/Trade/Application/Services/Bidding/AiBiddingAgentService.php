<?php

namespace Modules\Trade\Application\Services\Bidding;

use Illuminate\Support\Facades\DB;
use Modules\Banking\Application\DTOs\PostingDTO;
use Modules\Banking\Application\DTOs\PostingEntryDTO;
use Modules\Banking\Application\Services\LedgerService;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;

class AiBiddingAgentService
{
    public function __construct(
        protected ?LedgerService $ledgerService = null
    ) {
        $this->ledgerService = $ledgerService ?? app(LedgerService::class);
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
        if ($buyerRiskScore > (float) $agent->max_risk_score) {
            throw new \RuntimeException("Buyer risk score ({$buyerRiskScore}) exceeds agent safety threshold ({$agent->max_risk_score})");
        }

        // Deterministic price calculation: cost = commodity + logistics
        $totalCost = $commodityFeedPriceIdr + $logisticsCostIdr;
        $targetMarginMultiplier = 1.0 + ((float) $agent->target_margin_pct / 100.0);
        $rawBid = (int) round($totalCost * $targetMarginMultiplier);

        // Clamp to agent configured boundaries
        if ($rawBid < $agent->min_price_floor_idr || $rawBid > $agent->max_price_ceiling_idr) {
            throw new \RuntimeException("Optimal bid price ({$rawBid}) falls outside agent allowed boundaries [{$agent->min_price_floor_idr} - {$agent->max_price_ceiling_idr}]");
        }

        // 84.3 Standard contract commercial clause drafts
        $clauses = [
            'payment_terms' => 'NET_30_LC',
            'delivery_incoterm' => 'FOB_JAKARTA',
            'penalty_rate_per_day' => '0.001',
            'force_majeure' => 'ICC_FORCE_MAJEURE_2020',
        ];

        $runCode = 'RUN-'.strtoupper(bin2hex(random_bytes(6)));
        $evalHash = hash('sha256', "{$agent->agent_code}:{$tenderCode}:{$commodityFeedPriceIdr}:{$logisticsCostIdr}:{$rawBid}:".json_encode($clauses));

        return BidRun::create([
            'run_code' => $runCode,
            'bidding_agent_id' => $agent->id,
            'tender_reference_code' => $tenderCode,
            'commodity_feed_price_idr' => $commodityFeedPriceIdr,
            'estimated_logistics_cost_idr' => $logisticsCostIdr,
            'buyer_risk_score' => $buyerRiskScore,
            'optimal_bid_price_idr' => $rawBid,
            'clause_drafts' => $clauses,
            'evaluation_hash' => $evalHash,
        ]);
    }

    /**
     * Create draft submission requiring four-eyes approval
     */
    public function prepareSubmission(BidRun $run): BidSubmission
    {
        return BidSubmission::create([
            'submission_code' => 'SUB-'.strtoupper(bin2hex(random_bytes(6))),
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
                throw new \RuntimeException("Cannot approve submission in status {$locked->status}");
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
                throw new \RuntimeException('Only submitted bids can be marked as WON');
            }

            $contractCode = 'CTR-TENDER-'.strtoupper(bin2hex(random_bytes(6)));

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
                entries: [
                    PostingEntryDTO::forCode('trd:tender_contract_ar:IDR', 'IDR', $totalValue),
                    PostingEntryDTO::forCode('trd:tender_revenue_unearned:IDR', 'IDR', -$totalValue),
                ],
                referenceType: 'TENDER_CONTRACT',
                referenceId: $contractCode,
            ));

            return $locked;
        });
    }
}
