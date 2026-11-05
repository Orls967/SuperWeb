<?php

declare(strict_types=1);

namespace Modules\Syariah\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Syariah\Application\Services\IslamicLetterOfCreditService;
use Modules\Syariah\Application\Services\MurabahahSupplyChainService;
use Modules\Syariah\Application\Services\SalamTradeService;
use Modules\Syariah\Application\Services\TawarruqFxService;
use Modules\Syariah\Application\Services\TradeCustodyService;
use Modules\Syariah\Domain\Models\SybLetterOfCredit;
use Modules\Syariah\Domain\Models\SybParallelSalamHedge;
use Modules\Syariah\Domain\Models\SybSalamContract;
use Modules\Syariah\Domain\Models\SybScfFacility;
use Modules\Syariah\Domain\Models\SybScfInvoice;
use Modules\Syariah\Domain\Models\SybTawarruqOrder;
use Modules\Syariah\Domain\Models\SybTradeCustodyDocument;

final class IslamicTradeController extends Controller
{
    public function __construct(
        private readonly IslamicLetterOfCreditService $lcService,
        private readonly SalamTradeService $salamService,
        private readonly MurabahahSupplyChainService $scfService,
        private readonly TawarruqFxService $tawarruqService,
        private readonly TradeCustodyService $custodyService,
    ) {}

    public function index(): View
    {
        $stats = [
            'total_lc_count' => SybLetterOfCredit::count(),
            'total_lc_volume_idr' => (int) SybLetterOfCredit::sum('amount_cents'),
            'total_salam_count' => SybSalamContract::count(),
            'total_salam_volume_idr' => (int) SybSalamContract::sum('advance_payment_cents'),
            'total_scf_facility_count' => SybScfFacility::count(),
            'total_scf_invoices_idr' => (int) SybScfInvoice::sum('total_sale_price_cents'),
            'total_tawarruq_orders' => SybTawarruqOrder::count(),
            'verified_custody_count' => SybTradeCustodyDocument::whereNotNull('verified_qabdh_at')->count(),
            'pending_qabdh_count' => SybTradeCustodyDocument::whereNull('verified_qabdh_at')->count(),
        ];

        $recentLcs = SybLetterOfCredit::latest()->limit(5)->get();
        $recentSalams = SybSalamContract::latest()->limit(5)->get();

        return view('syariah::trade.index', compact('stats', 'recentLcs', 'recentSalams'));
    }

    public function lcIndex(): View
    {
        $lcs = SybLetterOfCredit::with('istisnaContracts')->latest()->paginate(15);

        return view('syariah::trade.lc_index', compact('lcs'));
    }

    public function lcShow(int $id): View
    {
        $lc = SybLetterOfCredit::with(['istisnaContracts.milestones'])->findOrFail($id);

        return view('syariah::trade.lc_show', compact('lc'));
    }

    public function salamIndex(): View
    {
        $salams = SybSalamContract::with(['parallelHedges', 'deliveries'])->latest()->paginate(15);
        $hedges = SybParallelSalamHedge::with('salamContract')->latest()->limit(10)->get();

        return view('syariah::trade.salam_index', compact('salams', 'hedges'));
    }

    public function salamShow(int $id): View
    {
        $salam = SybSalamContract::with(['parallelHedges', 'deliveries'])->findOrFail($id);

        return view('syariah::trade.salam_show', compact('salam'));
    }

    public function scfIndex(): View
    {
        $facilities = SybScfFacility::with('invoices')->latest()->paginate(15);

        return view('syariah::trade.scf_index', compact('facilities'));
    }

    public function scfShow(int $id): View
    {
        $facility = SybScfFacility::with(['invoices.repayments'])->findOrFail($id);

        return view('syariah::trade.scf_show', compact('facility'));
    }

    public function tawarruqIndex(): View
    {
        $orders = SybTawarruqOrder::with(['commodityLot', 'transactions'])->latest()->paginate(15);

        return view('syariah::trade.tawarruq_index', compact('orders'));
    }

    public function custodyIndex(): View
    {
        $documents = SybTradeCustodyDocument::with('inspections')->latest()->paginate(15);

        return view('syariah::trade.custody_index', compact('documents'));
    }

    public function calculator(Request $request): View
    {
        $sourceCurrency = $request->input('source_currency', 'IDR');
        $principalIdr = (int) $request->input('principal_idr', 1_550_000_000);
        $targetCurrency = $request->input('target_currency', 'USD');
        $spotRateBps = (int) $request->input('spot_rate_bps', 1_550_000);
        $tenorDays = (int) $request->input('tenor_days', 90);
        $markupRateBps = (int) $request->input('markup_rate_bps', 600);
        $brokerFeeIdr = (int) $request->input('broker_fee_idr', 2_500_000);

        $targetReceived = (int) intdiv($principalIdr * 10000, max(1, $spotRateBps));
        $markupProfit = (int) intdiv($principalIdr * $markupRateBps * $tenorDays, 365 * 10000);
        $totalDeferredPrice = $principalIdr + $markupProfit + $brokerFeeIdr;
        $effectiveRate = (int) intdiv($totalDeferredPrice * 10000, max(1, $targetReceived));

        return view('syariah::trade.calculator', compact(
            'sourceCurrency',
            'principalIdr',
            'targetCurrency',
            'spotRateBps',
            'tenorDays',
            'markupRateBps',
            'brokerFeeIdr',
            'targetReceived',
            'markupProfit',
            'totalDeferredPrice',
            'effectiveRate'
        ));
    }
}
