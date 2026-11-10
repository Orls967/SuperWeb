<?php

declare(strict_types=1);

namespace Modules\Intercompany\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Intercompany\Application\Actions\AllocateCostSharingAction;
use Modules\Intercompany\Application\Actions\CreateMirroredIntercompanyTransactionAction;
use Modules\Intercompany\Application\Actions\IssueIntercompanyLoanAction;
use Modules\Intercompany\Application\Actions\TransferIntercompanyAssetAction;
use Modules\Intercompany\Application\Actions\TransferIntercompanyStockAction;
use Modules\Intercompany\Application\Reports\IntercompanyMatrixReportExporter;
use Modules\Intercompany\Domain\Models\CostSharingAgreement;
use Modules\Intercompany\Domain\Models\EliminationEntry;
use Modules\Intercompany\Domain\Models\IntercompanyAssetTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyEntity;
use Modules\Intercompany\Domain\Models\IntercompanyLoan;
use Modules\Intercompany\Domain\Models\IntercompanyStockTransfer;
use Modules\Intercompany\Domain\Models\IntercompanyTransaction;
use Modules\Intercompany\Domain\Models\SubsidiaryNci;
use Modules\Intercompany\Domain\Models\TransferPricingRule;
use Modules\Intercompany\Http\Requests\AllocateCostSharingRequest;
use Modules\Intercompany\Http\Requests\IssueLoanRequest;
use Modules\Intercompany\Http\Requests\StoreIntercompanyTransactionRequest;
use Modules\Intercompany\Http\Requests\TransferAssetRequest;
use Modules\Intercompany\Http\Requests\TransferStockRequest;

class IntercompanyController extends Controller
{
    public function index(Request $request): View
    {
        $entities = IntercompanyEntity::where('is_active', true)->orderBy('entity_code')->get();
        $transactions = IntercompanyTransaction::orderByDesc('created_at')->limit(10)->get();
        $loans = IntercompanyLoan::orderByDesc('created_at')->limit(10)->get();
        $rules = TransferPricingRule::orderByDesc('created_at')->limit(10)->get();
        $eliminations = EliminationEntry::orderByDesc('created_at')->limit(10)->get();
        $ncis = SubsidiaryNci::orderByDesc('created_at')->limit(10)->get();

        return view('intercompany::index', compact('entities', 'transactions', 'loans', 'rules', 'eliminations', 'ncis'));
    }

    public function transactions(Request $request): View
    {
        $transactions = IntercompanyTransaction::orderByDesc('created_at')->paginate(25);

        return view('intercompany::transactions', compact('transactions'));
    }

    public function loans(Request $request): View
    {
        $loans = IntercompanyLoan::with('schedules')->orderByDesc('created_at')->paginate(25);

        return view('intercompany::loans', compact('loans'));
    }

    public function eliminations(Request $request): View
    {
        $eliminations = EliminationEntry::orderByDesc('created_at')->paginate(25);

        return view('intercompany::elimination', compact('eliminations'));
    }

    public function transferPricing(Request $request): View
    {
        $rules = TransferPricingRule::with('benchmarks')->orderBy('rule_code')->get();

        return view('intercompany::transfer_pricing', compact('rules'));
    }

    public function costSharing(Request $request): View
    {
        $agreements = CostSharingAgreement::with('allocations')->orderBy('agreement_code')->get();

        return view('intercompany::cost_sharing', compact('agreements'));
    }

    public function assetTransfers(Request $request): View
    {
        $assetTransfers = IntercompanyAssetTransfer::orderByDesc('created_at')->paginate(15);
        $stockTransfers = IntercompanyStockTransfer::orderByDesc('created_at')->paginate(15);

        return view('intercompany::asset_transfers', compact('assetTransfers', 'stockTransfers'));
    }

    public function segments(Request $request): View
    {
        $ncis = SubsidiaryNci::with('dividends')->orderBy('subsidiary_entity')->get();

        return view('intercompany::segments', compact('ncis'));
    }

    public function storeTransaction(
        StoreIntercompanyTransactionRequest $request,
        CreateMirroredIntercompanyTransactionAction $action
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['created_by'] = $request->user()?->id;

        $action->execute($validated);

        return redirect()->route('intercompany.transactions')
            ->with('status', 'Transaksi intercompany cermin berhasil dicatat dan diposting.');
    }

    public function issueLoan(
        IssueLoanRequest $request,
        IssueIntercompanyLoanAction $action
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['operator_id'] = $request->user()?->id;

        $action->execute($validated);

        return redirect()->route('intercompany.loans')
            ->with('status', 'Fasilitas pinjaman antar-entitas berhasil diterbitkan dengan jadwal amortisasi.');
    }

    public function allocateCosts(
        AllocateCostSharingRequest $request,
        AllocateCostSharingAction $action
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['operator_id'] = $request->user()?->id;

        $action->execute($validated);

        return redirect()->route('intercompany.cost-sharing')
            ->with('status', 'Alokasi pembagian biaya (CSA) berhasil dialokasikan dan diposting ke ledger.');
    }

    public function transferAsset(
        TransferAssetRequest $request,
        TransferIntercompanyAssetAction $action
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['operator_id'] = $request->user()?->id;

        $action->execute($validated);

        return redirect()->route('intercompany.asset-transfers')
            ->with('status', 'Transfer aset tetap antar-perusahaan berhasil dicatat.');
    }

    public function transferStock(
        TransferStockRequest $request,
        TransferIntercompanyStockAction $action
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['operator_id'] = $request->user()?->id;

        $action->execute($validated);

        return redirect()->route('intercompany.asset-transfers')
            ->with('status', 'Transfer persediaan stok barang antar-perusahaan berhasil diproses.');
    }

    public function exportMatrix(Request $request, IntercompanyMatrixReportExporter $exporter): Response
    {
        $period = (string) ($request->query('period') ?: now()->format('Y-m'));
        $res = $exporter->export($period);

        return response($res['content'], 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$res['filename']}\"",
            'X-SHA256-Checksum' => $res['sha256'],
        ]);
    }
}
