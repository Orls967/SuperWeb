<?php

declare(strict_types=1);

namespace Modules\Trade\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Trade\Application\Services\Bidding\AiBiddingAgentService;
use Modules\Trade\Domain\Models\Bidding\BiddingAgent;
use Modules\Trade\Domain\Models\Bidding\BidRun;
use Modules\Trade\Domain\Models\Bidding\BidSubmission;
use Throwable;

class BiddingController extends Controller
{
    public function __construct(
        private readonly AiBiddingAgentService $service
    ) {}

    public function index(): View
    {
        return view('trade::bidding', [
            'agents' => BiddingAgent::orderByDesc('created_at')->get(),
            'runs' => BidRun::with('agent')->orderByDesc('created_at')->limit(50)->get(),
            'submissions' => BidSubmission::with('run.agent')->orderByDesc('created_at')->limit(50)->get(),
            'summary' => $this->service->getBidDeskSummary(),
        ]);
    }

    public function createAgent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'agent_code' => 'required|string|max:32|unique:trd_bidding_agents,agent_code',
            'entity_code' => 'required|string|max:32',
            'commodity_code' => 'required|string|max:32',
            'min_price_floor_idr' => 'required|integer|min:1000',
            'max_price_ceiling_idr' => 'required|integer|min:1000',
            'target_margin_pct' => 'nullable|numeric|min:1|max:100',
            'max_risk_score' => 'nullable|numeric|min:0.01|max:1.0',
        ]);

        try {
            $agent = $this->service->createAgent(
                agentCode: $data['agent_code'],
                entityCode: $data['entity_code'],
                commodityCode: $data['commodity_code'],
                minPriceFloorIdr: (int) $data['min_price_floor_idr'],
                maxPriceCeilingIdr: (int) $data['max_price_ceiling_idr'],
                targetMarginPct: isset($data['target_margin_pct']) ? (float) $data['target_margin_pct'] : 15.0,
                maxRiskScore: isset($data['max_risk_score']) ? (float) $data['max_risk_score'] : 0.40
            );

            return back()->with('success', "Agent {$agent->agent_code} berhasil dibuat.");
        } catch (Throwable $e) {
            return back()->withErrors(['agent' => $e->getMessage()]);
        }
    }

    public function evaluateRun(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bidding_agent_id' => 'required|integer|exists:trd_bidding_agents,id',
            'tender_reference_code' => 'required|string|max:64',
            'commodity_feed_price_idr' => 'required|integer|min:1000',
            'estimated_logistics_cost_idr' => 'required|integer|min:0',
            'buyer_risk_score' => 'required|numeric|min:0|max:1.0',
        ]);

        try {
            $agent = BiddingAgent::findOrFail((int) $data['bidding_agent_id']);
            $run = $this->service->runEvaluation(
                agent: $agent,
                tenderCode: $data['tender_reference_code'],
                commodityFeedPriceIdr: (int) $data['commodity_feed_price_idr'],
                logisticsCostIdr: (int) $data['estimated_logistics_cost_idr'],
                buyerRiskScore: (float) $data['buyer_risk_score']
            );

            return back()->with('success', "Evaluasi AI lelang {$run->run_code} selesai. Rekomendasi penawaran: Rp ".number_format($run->optimal_bid_price_idr));
        } catch (Throwable $e) {
            return back()->withErrors(['run' => $e->getMessage()]);
        }
    }

    public function prepareSubmission(BidRun $run): RedirectResponse
    {
        try {
            $sub = $this->service->prepareSubmission($run);

            return back()->with('success', "Draft penawaran {$sub->submission_code} disiapkan (Menunggu Four-Eyes Approval).");
        } catch (Throwable $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }
    }

    public function approveSubmission(Request $request, BidSubmission $submission): RedirectResponse
    {
        try {
            $userId = (int) $request->user()->id;
            $approved = $this->service->approveAndSubmit($submission, $userId);

            return back()->with('success', "Penawaran {$approved->submission_code} disetujui four-eyes dan resmi disubmit ke panitia lelang.");
        } catch (Throwable $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }
    }

    public function recordResult(Request $request, BidSubmission $submission): RedirectResponse
    {
        $data = $request->validate([
            'result' => 'required|in:WON,LOST',
            'feedback_reason' => 'nullable|string|max:255',
        ]);

        try {
            if ($data['result'] === 'WON') {
                $won = $this->service->recordWonAuction($submission);

                return back()->with('success', "Selamat! Lelang DIMENANGKAN. Kontrak {$won->created_contract_code} terbit & komitmen anggaran diposting.");
            } else {
                $lost = $this->service->recordLostAuction($submission, $data['feedback_reason'] ?? 'Kompetitor menawarkan harga lebih rendah');

                return back()->with('info', "Lelang {$lost->submission_code} ditandai KALAH. Umpan balik model dicatat.");
            }
        } catch (Throwable $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }
    }
}
