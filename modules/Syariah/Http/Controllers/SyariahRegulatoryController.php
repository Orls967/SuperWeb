<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\SyariahRecoveryWaterfallService;
use Modules\Syariah\Application\Services\SyariahRegulatoryReportService;
use Modules\Syariah\Domain\Models\SybCollateralAuction;
use Modules\Syariah\Domain\Models\SybRecoveryReceipt;
use Modules\Syariah\Domain\Models\SybWriteoffApproval;

final class SyariahRegulatoryController extends Controller
{
    public function __construct(
        private readonly SyariahRegulatoryReportService $reportService,
        private readonly SyariahRecoveryWaterfallService $recoveryService,
    ) {}

    public function lbbsIndex(Request $request): View
    {
        $period = $request->query('period', Carbon::now()->format('Y-m'));
        $report = $this->reportService->generateLbbsReport((string) $period);

        return view('syariah::ops.lbbs_index', [
            'report' => $report,
            'period' => $period,
        ]);
    }

    public function recoveryIndex(): View
    {
        $writeoffs = SybWriteoffApproval::orderByDesc('id')->paginate(10, ['*'], 'writeoffs');
        $auctions = SybCollateralAuction::orderByDesc('id')->paginate(10, ['*'], 'auctions');
        $receipts = SybRecoveryReceipt::orderByDesc('id')->paginate(10, ['*'], 'receipts');

        return view('syariah::ops.recovery_index', [
            'writeoffs' => $writeoffs,
            'auctions' => $auctions,
            'receipts' => $receipts,
        ]);
    }

    public function settleAuction(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'winning_bid_minor' => ['required', 'integer', 'min:1'],
            'buyer_ref' => ['required', 'string', 'max:64'],
            'outstanding_debt_minor' => ['required', 'integer', 'min:0'],
        ]);

        $auction = SybCollateralAuction::findOrFail($id);

        $settled = $this->recoveryService->settleAuction(
            auctionRef: $auction->auction_ref,
            winningBidMinor: (int) $validated['winning_bid_minor'],
            buyerRef: $validated['buyer_ref'],
            outstandingDebtMinor: (int) $validated['outstanding_debt_minor']
        );

        return redirect()->route('syariah.ops.recovery.index')
            ->with('success', "Lelang {$settled->auction_ref} berhasil diselesaikan. Kelebihan dikembalikan: Rp ".number_format($settled->surplus_refunded_to_customer_minor / 100, 2));
    }
}
