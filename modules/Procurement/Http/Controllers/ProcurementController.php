<?php

declare(strict_types=1);

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Procurement\Application\Services\InboundShipmentService;
use Modules\Procurement\Application\Services\ProcurementService;
use Modules\Procurement\Domain\Models\BudgetCenter;
use Modules\Procurement\Domain\Models\PurchaseOrder;
use Modules\Procurement\Domain\Models\Quote;
use Modules\Procurement\Domain\Models\Requisition;
use Modules\Procurement\Domain\Models\Rfq;
use Modules\Procurement\Domain\Models\Tender;
use Modules\Procurement\Domain\Models\TenderBid;
use Modules\Supplier\Domain\Models\Supplier;

/**
 * Procurement UI (33.1–33.8): PR, RFQ, Tender, PO, encumbrance, dashboard.
 */
class ProcurementController extends Controller
{
    public function __construct(
        private readonly ProcurementService $service,
        private readonly InboundShipmentService $inbound,
    ) {}

    // ── 33.8 Dashboard ──────────────────────────────────────────────────

    public function dashboard(): View
    {
        return view('procurement::dashboard', [
            'stats' => $this->service->dashboard(),
            'prList' => Requisition::with('budgetCenter')->latest()->limit(8)->get(),
            'poList' => PurchaseOrder::with('supplier')->latest()->limit(8)->get(),
            'rfqList' => Rfq::latest()->limit(5)->get(),
            'budgetCenters' => BudgetCenter::where('is_active', true)->get(),
        ]);
    }

    // ── 33.1 PR ─────────────────────────────────────────────────────────

    public function storeRequisition(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'notes' => 'nullable|string|max:1000',
            'source' => 'nullable|in:manual,mrp,reorder_point',
            'budget_center_id' => 'nullable|integer|exists:prc_budget_centers,id',
            'legal_entity_id' => 'nullable|uuid|exists:pty_legal_entities,id',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:300',
            'lines.*.qty' => 'nullable|integer|min:1',
            'lines.*.unit' => 'nullable|string|max:32',
            'lines.*.estimated_unit_price_idr' => 'nullable|integer|min:0',
            'lines.*.supplier_item_id' => 'nullable|integer',
        ]);

        $pr = $this->service->createRequisition($data, $request->user());

        return redirect()
            ->route('procurement.requisitions.show', $pr)
            ->with('success', "PR [{$pr->number}] dibuat dan diajukan approval.");
    }

    public function approveRequisition(Requisition $requisition): RedirectResponse
    {
        $this->service->approveRequisition($requisition);

        return back()->with('success', 'PR disetujui; anggaran dicatat (encumbrance).');
    }

    public function showRequisition(Requisition $requisition): View
    {
        $requisition->load('lines', 'budgetCenter', 'legalEntity');

        return view('procurement::requisition-show', ['requisition' => $requisition]);
    }

    // ── 33.2 RFQ ────────────────────────────────────────────────────────

    public function storeRfq(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'requisition_id' => 'nullable|uuid|exists:prc_requisitions,id',
            'closes_at' => 'required|date',
            'terms' => 'nullable|string|max:1000',
            'supplier_ids' => 'required|array|min:1',
            'supplier_ids.*' => 'uuid|exists:sup_suppliers,id',
        ]);

        $rfq = $this->service->createRfq($data, $data['supplier_ids'], $request->user());

        return redirect()
            ->route('procurement.rfqs.show', $rfq)
            ->with('success', "RFQ [{$rfq->number}] dibuat — ".count($data['supplier_ids']).' pemasok diundang.');
    }

    public function showRfq(Rfq $rfq): View
    {
        $rfq->load('invitations', 'quotes.supplier', 'requisition');

        return view('procurement::rfq-show', [
            'rfq' => $rfq,
            'comparison' => $this->service->compareQuotes($rfq),
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /** Pemasok mengirim penawaran. */
    public function storeQuote(Request $request, Rfq $rfq): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|uuid|exists:sup_suppliers,id',
            'total_price_idr' => 'required|integer|min:0',
            'lead_time_days' => 'required|integer|min:1|max:365',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $this->service->submitQuote($rfq, $supplier, $data);

        return back()->with('success', 'Penawaran tersimpan.');
    }

    /** Tetapkan pemenang dengan alasan tercatat. */
    public function awardQuote(Request $request, Rfq $rfq): RedirectResponse
    {
        $data = $request->validate([
            'quote_id' => 'required|integer|exists:prc_quotes,id',
            'reason' => 'required|string|max:500',
        ]);

        $quote = Quote::findOrFail($data['quote_id']);
        $this->service->awardQuote($rfq, $quote, $data['reason'], $request->user());

        return back()->with('success', 'Pemenang RFQ ditetapkan dengan alasan tercatat.');
    }

    // ── 33.3 Tender ─────────────────────────────────────────────────────

    public function storeTender(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'type' => 'required|in:closed,open',
            'bids_close_at' => 'required|date',
            'bids_open_at' => 'nullable|date',
            'criteria' => 'nullable|array',
        ]);

        $tender = $this->service->createTender($data, $request->user());

        return redirect()
            ->route('procurement.tenders.show', $tender)
            ->with('success', "Tender [{$tender->number}] dibuka.");
    }

    public function showTender(Tender $tender): View
    {
        $tender->load('bids.supplier');

        return view('procurement::tender-show', ['tender' => $tender]);
    }

    public function sealBid(Request $request, Tender $tender): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|uuid|exists:sup_suppliers,id',
            'offer' => 'required|json|max:4000',
        ]);

        $supplier = Supplier::findOrFail($data['supplier_id']);
        $this->service->sealBid($tender, $supplier, json_decode($data['offer'], true) ?: []);

        return back()->with('success', 'Segel penawaran terkirim (blind bid).');
    }

    public function openBids(Tender $tender): RedirectResponse
    {
        $opened = $this->service->openBids($tender);

        return back()->with('success', "{$opened} segel penawaran dibuka bersamaan.");
    }

    public function evaluateTender(Tender $tender): RedirectResponse
    {
        $results = $this->service->evaluateTender($tender);

        return back()->with('success', count($results).' bid dievaluasi berbobot.');
    }

    public function awardTender(Request $request, Tender $tender): RedirectResponse
    {
        $data = $request->validate([
            'bid_id' => 'required|integer|exists:prc_tender_bids,id',
            'reason' => 'required|string|max:500',
        ]);

        $bid = TenderBid::findOrFail($data['bid_id']);
        $this->service->awardTender($tender, $bid, $request->user(), $data['reason']);

        return back()->with('success', 'Pemenang tender diajukan approval.');
    }

    // ── 33.4–33.5 PO ────────────────────────────────────────────────────

    public function storePurchaseOrder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|uuid|exists:sup_suppliers,id',
            'title' => 'required|string|max:200',
            'kind' => 'nullable|in:standard,blanket,call_off,import',
            'requisition_id' => 'nullable|uuid|exists:prc_requisitions,id',
            'rfq_id' => 'nullable|uuid|exists:prc_rfqs,id',
            'tender_id' => 'nullable|uuid|exists:prc_tenders,id',
            'contract_id' => 'nullable|uuid|exists:ctr_contracts,id',
            'blanket_po_id' => 'nullable|uuid|exists:prc_purchase_orders,id',
            'budget_center_id' => 'nullable|integer|exists:prc_budget_centers,id',
            'currency' => 'nullable|string|size:3',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:300',
            'lines.*.qty' => 'nullable|integer|min:1',
            'lines.*.unit' => 'nullable|string|max:32',
            'lines.*.unit_price' => 'nullable|integer|min:0',
        ]);

        $po = $this->service->createPurchaseOrder($data, $request->user());

        return redirect()
            ->route('procurement.pos.show', $po)
            ->with('success', "PO [{$po->number}] dibuat.".((int) $po->total_amount > 0 ? ' Anggaran dikunci (encumbrance).' : ''));
    }

    public function showPurchaseOrder(PurchaseOrder $po): View
    {
        $po->load('lines', 'supplier', 'versions', 'budgetCenter');

        return view('procurement::po-show', ['po' => $po]);
    }

    public function revisePurchaseOrder(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
            'title' => 'nullable|string|max:200',
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $reason = $data['reason'];
        unset($data['reason']);

        $this->service->revisePurchaseOrder($po, $data, $request->user(), $reason);

        return back()->with('success', 'Revisi PO diajukan approval (versi baru direkam).');
    }

    public function closePurchaseOrder(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->service->closePurchaseOrder($po, $data['reason']);

        return back()->with('success', 'PO ditutup; sisa encumbrance dilepas.');
    }

    public function cancelPurchaseOrder(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $this->service->cancelPurchaseOrder($po, $data['reason']);

        return back()->with('success', 'PO dibatalkan; encumbrance dilepas.');
    }

    /** 33.5 Profil impor (Incoterm, kurs, landed cost — simulasi). */
    public function storeImportProfile(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $data = $request->validate([
            'currency' => 'required|string|size:3',
            'incoterm' => 'required|in:EXW,FOB,CFR,CIF,DAP,DDP',
            'fx_rate' => 'required|numeric|min:0.000001|max:1000000',
            'origin_port' => 'nullable|string|max:100',
            'destination_port' => 'nullable|string|max:100',
            'freight_estimate_idr' => 'nullable|integer|min:0',
            'insurance_estimate_idr' => 'nullable|integer|min:0',
            'duty_estimate_idr' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:1000',
        ]);

        $profile = $this->service->createImportProfile($po, $data);

        return back()->with('success', 'Profil impor tersimpan; landed cost estimasi = '.number_format($profile->landed_cost_estimate_idr).' IDR (simulasi).');
    }

    /** 33.7 Jadwalkan shipment inbound dari PO (via kontrak Logistics). */
    public function bookInbound(Request $request, PurchaseOrder $po): RedirectResponse
    {
        $data = $request->validate([
            'origin_code' => 'required|string|max:40',
            'street' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'consignee_name' => 'nullable|string|max:100',
            'consignee_phone' => 'nullable|string|max:20',
        ]);

        $result = $this->inbound->bookInbound($po, $request->user(), $data);

        return back()->with('success', 'Shipment masuk dibuat: '.$result['tracking_number']);
    }
}
