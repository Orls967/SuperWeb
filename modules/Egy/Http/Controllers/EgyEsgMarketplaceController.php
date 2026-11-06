<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\SettleEsgMarketplaceTradeAction;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketplaceService;
use Modules\Egy\Domain\Models\EsgMarketplaceOrder;

final class EgyEsgMarketplaceController extends Controller
{
    public function __construct(
        private readonly CarbonTradingAndEsgMarketplaceService $marketplaceService,
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->marketplaceService->getEsgMarketplaceOverview();

        return view('egy::esg_marketplace.index', $overview);
    }

    public function purchase(Request $request, SettleEsgMarketplaceTradeAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'listing_id' => 'required|integer|exists:egy_esg_marketplace_listings,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $buyerPartyId = (int) ($user->party_id ?? $user->id ?? 1);

        $idempotencyKey = sprintf('ESG-BUY-%d-%d-%s', $validated['listing_id'], $buyerPartyId, date('YmdHis'));

        $order = $action->execute(
            listingId: (int) $validated['listing_id'],
            buyerPartyId: $buyerPartyId,
            purchaseQuantity: (int) $validated['quantity'],
            idempotencyKey: $idempotencyKey
        );

        return redirect()->route('egy.esg.marketplace.index')->with(
            'success',
            "Pesanan {$order->order_number} berhasil diproses. Kode klaim: {$order->redemption_code}"
        );
    }

    public function export(): Response
    {
        $orders = EsgMarketplaceOrder::with('listing')->latest()->get();
        $headers = [
            'Order Number',
            'Listing Title',
            'Category',
            'Buyer Party ID',
            'Quantity',
            'Total (IDR)',
            'Claim Status',
            'Redemption Code',
            'Ledger Tx ID',
            'SHA-256 Hash',
        ];

        $rows = array_map(function ($o) {
            return [
                $o->order_number,
                $o->listing ? $o->listing->title : '',
                $o->listing ? $o->listing->category : '',
                $o->buyer_party_id,
                $o->purchased_quantity,
                $o->total_amount_idr,
                $o->claim_status,
                $o->redemption_code,
                $o->ledger_transaction_id,
                $o->certificate_hash,
            ];
        }, iterator_to_array($orders));

        $csv = $this->marketplaceService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="esg-marketplace-orders.csv"',
        ]);
    }
}
