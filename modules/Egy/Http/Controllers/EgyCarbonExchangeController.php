<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\MatchCarbonOrdersAction;
use Modules\Egy\Application\Services\CarbonTradingAndEsgMarketplaceService;
use Modules\Egy\Domain\Models\CarbonOrder;
use Modules\Egy\Domain\Models\CarbonTrade;

final class EgyCarbonExchangeController extends Controller
{
    public function __construct(
        private readonly CarbonTradingAndEsgMarketplaceService $marketplaceService,
    ) {}

    public function index(Request $request): View
    {
        $overview = $this->marketplaceService->getCarbonExchangeOverview();

        return view('egy::carbon.index', $overview);
    }

    public function storeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'side' => 'required|string|in:BUY,SELL',
            'vintage_year' => 'nullable|string|max:4',
            'quantity_kg_co2e' => 'required|integer|min:100',
            'limit_price_minor_per_ton' => 'required|integer|min:1000',
        ]);

        $user = $request->user();
        $partyId = (int) ($user->party_id ?? $user->id ?? 1);

        $orderNumber = sprintf('ORD-%s-%s-%d', $validated['side'], date('YmdHis'), $partyId);

        CarbonOrder::create([
            'order_number' => $orderNumber,
            'party_id' => $partyId,
            'side' => $validated['side'],
            'vintage_year' => $validated['vintage_year'] ?? date('Y'),
            'quantity_kg_co2e' => (int) $validated['quantity_kg_co2e'],
            'filled_kg_co2e' => 0,
            'limit_price_minor_per_ton' => (int) $validated['limit_price_minor_per_ton'],
            'status' => 'open',
        ]);

        return redirect()->route('egy.carbon.index')->with('success', "Order {$orderNumber} berhasil didaftarkan ke buku pesanan.");
    }

    public function matchOrders(MatchCarbonOrdersAction $action): RedirectResponse
    {
        $cycleKey = sprintf('MANUAL-MATCH-%s', date('YmdHis'));
        $trades = $action->execute($cycleKey);

        return redirect()->route('egy.carbon.index')->with(
            'success',
            sprintf('Pencocokan orderbook selesai: %d transaksi dieksekusi.', count($trades))
        );
    }

    public function exportTrades(): Response
    {
        $trades = CarbonTrade::with(['buyOrder', 'sellOrder'])->latest()->get();
        $headers = [
            'Trade Number',
            'Buyer Party ID',
            'Seller Party ID',
            'Matched (kg CO2e)',
            'Price (IDR/ton)',
            'Total Value (IDR)',
            'Platform Fee (IDR)',
            'Ledger Tx ID',
            'Settlement Status',
            'SHA-256 Hash',
        ];

        $rows = array_map(function ($t) {
            return [
                $t->trade_number,
                $t->buyer_party_id,
                $t->seller_party_id,
                $t->matched_kg_co2e,
                $t->execution_price_minor_per_ton,
                $t->total_amount_idr,
                $t->platform_fee_idr,
                $t->ledger_transaction_id,
                $t->settlement_status,
                $t->certificate_hash,
            ];
        }, iterator_to_array($trades));

        $csv = $this->marketplaceService->formatAsCsv($headers, $rows);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="carbon-trades-audit.csv"',
        ]);
    }
}
