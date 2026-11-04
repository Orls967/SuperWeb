<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Trade\Application\Services\Bidding\AiBiddingAgentService;
use Throwable;

class BidAssistantController extends Controller
{
    public function __construct(
        private readonly AiBiddingAgentService $service
    ) {}

    public function index(): View
    {
        return view('procurement::bid-assistant.index', [
            'agents' => $this->service->getAgents(),
            'runs' => $this->service->getRecentRuns(50),
            'submissions' => $this->service->getRecentSubmissions(50),
            'summary' => $this->service->getBidDeskSummary(),
        ]);
    }

    public function evaluate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'bidding_agent_id' => 'required|integer|exists:trd_bidding_agents,id',
            'tender_reference_code' => 'required|string|max:64',
            'commodity_feed_price_idr' => 'required|integer|min:1000',
            'estimated_logistics_cost_idr' => 'required|integer|min:0',
            'buyer_risk_score' => 'required|numeric|min:0|max:1.0',
        ]);

        try {
            $agent = $this->service->findAgent((int) $data['bidding_agent_id']);
            $run = $this->service->runEvaluation(
                agent: $agent,
                tenderCode: $data['tender_reference_code'],
                commodityFeedPriceIdr: (int) $data['commodity_feed_price_idr'],
                logisticsCostIdr: (int) $data['estimated_logistics_cost_idr'],
                buyerRiskScore: (float) $data['buyer_risk_score']
            );

            return back()->with('success', "Evaluasi asisten tender {$run->run_code} selesai. Rekomendasi penawaran: Rp ".number_format($run->optimal_bid_price_idr));
        } catch (Throwable $e) {
            return back()->withErrors(['run' => $e->getMessage()]);
        }
    }

    public function prepare(Request $request, int|string $run): RedirectResponse
    {
        try {
            $runModel = $this->service->findRun((int) $run);
            $userId = $request->user() !== null ? (int) $request->user()->id : null;
            $sub = $this->service->prepareSubmission($runModel, $userId);

            return back()->with('success', "Draft penawaran {$sub->submission_code} berhasil disiapkan (Menunggu Four-Eyes Approval).");
        } catch (Throwable $e) {
            return back()->withErrors(['submission' => $e->getMessage()]);
        }
    }

    public function approve(Request $request, int|string $submission): RedirectResponse
    {
        try {
            $subModel = $this->service->findSubmission((int) $submission);
            $userId = (int) $request->user()->id;
            $approved = $this->service->approveAndSubmit($subModel, $userId);

            return back()->with('success', "Penawaran {$approved->submission_code} disetujui four-eyes oleh manager dan resmi disubmit ke tender.");
        } catch (Throwable $e) {
            return back()->withErrors(['approval' => $e->getMessage()]);
        }
    }

    public function result(Request $request, int|string $submission): RedirectResponse
    {
        $data = $request->validate([
            'result' => 'required|in:WON,LOST',
            'feedback_reason' => 'nullable|string|max:255',
        ]);

        try {
            $subModel = $this->service->findSubmission((int) $submission);
            if ($data['result'] === 'WON') {
                $won = $this->service->recordWonAuction($subModel);

                return back()->with('success', "Tender DIMENANGKAN! Kontrak {$won->created_contract_code} diterbitkan dan anggaran diposting.");
            }

            $lost = $this->service->recordLostAuction($subModel, $data['feedback_reason'] ?? 'Kompetitor menawarkan harga lebih kompetitif');

            return back()->with('info', "Tender {$lost->submission_code} ditandai KALAH. Umpan balik model dicatat.");
        } catch (Throwable $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }
    }
}
