<?php

declare(strict_types=1);

namespace Modules\B2b\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\B2b\Application\Actions\PublishWholesaleTierCatalogAction;
use Modules\B2b\Application\Actions\RunSurplusAssetDutchAuctionAction;
use Modules\B2b\Application\Actions\SettleMultiPartyB2bEscrowViaLedgerAction;
use Modules\B2b\Application\Services\B2bService;
use Modules\B2b\Domain\Models\B2bEscrowAccount;
use Modules\B2b\Domain\Models\SurplusAuction;
use Modules\B2b\Domain\Models\WholesaleCatalog;

class B2bOperationsController extends Controller
{
    public function dashboard(B2bService $service): View
    {
        $audit = $service->auditB2b();

        return view('b2b::b2b-operations-dashboard', compact('audit'));
    }

    public function wholesaleIndex(): View
    {
        $catalogs = WholesaleCatalog::latest()->take(30)->get();

        return view('b2b::wholesale-tier-catalog', compact('catalogs'));
    }

    public function publishWholesale(Request $request, PublishWholesaleTierCatalogAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'vendor_id' => 'required|string',
            'sku' => 'required|string',
            'product_name' => 'required|string',
            'category' => 'nullable|string',
            'base_price_idr' => 'required|integer|min:1',
            'min_order_qty' => 'nullable|integer|min:1',
            'available_stock' => 'nullable|integer|min:0',
        ]);

        $action->execute($validated);

        return redirect()->route('b2b.wholesale.index')->with('success', "SKU {$validated['sku']} successfully published to wholesale catalog.");
    }

    public function auctionIndex(): View
    {
        $auctions = SurplusAuction::latest()->take(30)->get();

        return view('b2b::surplus-dutch-auction', compact('auctions'));
    }

    public function createAuction(Request $request, RunSurplusAssetDutchAuctionAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'lot_number' => 'required|string',
            'seller_id' => 'required|string',
            'title' => 'required|string',
            'asset_type' => 'nullable|string',
            'starting_bid_idr' => 'required|integer|min:1',
            'reserve_price_idr' => 'required|integer|min:1',
        ]);

        $action->createDutchAuction($validated);

        return redirect()->route('b2b.auction.index')->with('success', "Dutch auction lot {$validated['lot_number']} launched.");
    }

    public function escrowIndex(): View
    {
        $escrows = B2bEscrowAccount::latest()->take(30)->get();

        return view('b2b::multi-party-escrow', compact('escrows'));
    }

    public function lockEscrow(Request $request, SettleMultiPartyB2bEscrowViaLedgerAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'reference_type' => 'required|string',
            'reference_id' => 'required|string',
            'buyer_id' => 'required|string',
            'seller_id' => 'required|string',
            'deposit_amount_idr' => 'required|integer|min:1',
        ]);

        $escrow = $action->lockEscrow($validated);

        return redirect()->route('b2b.escrow.index')->with('success', "Escrow {$escrow->escrow_number} locked in ledger.");
    }

    public function releaseEscrow(Request $request, SettleMultiPartyB2bEscrowViaLedgerAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'escrow_id' => 'required|string',
            'bast_document_id' => 'required|string',
            'release_amount_idr' => 'nullable|integer|min:1',
        ]);

        $escrow = $action->settleAndRelease($validated['escrow_id'], $validated['bast_document_id'], $validated['release_amount_idr'] ?? null);

        return redirect()->route('b2b.escrow.index')->with('success', "Escrow {$escrow->escrow_number} settled and released.");
    }
}
