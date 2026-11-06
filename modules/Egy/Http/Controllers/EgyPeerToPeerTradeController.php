<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Actions\SettlePeerToPeerEnergyTradeAction;
use Modules\Egy\Domain\Models\Microgrid;

final class EgyPeerToPeerTradeController extends Controller
{
    public function __construct(
        private readonly SettlePeerToPeerEnergyTradeAction $settleAction,
    ) {}

    public function index(): View
    {
        $microgrids = Microgrid::with('feeders')->get();

        return view('egy::p2p.index', compact('microgrids'));
    }

    public function trade(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'microgrid_id' => ['required', 'integer', 'exists:egy_microgrids,id'],
            'seller_party_id' => ['required', 'string', 'max:50'],
            'buyer_party_id' => ['required', 'string', 'max:50'],
            'energy_kwh' => ['required', 'integer', 'min:1'],
            'unit_price_idr' => ['required', 'integer', 'min:500'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $tradeResult = $this->settleAction->execute(
            microgridId: (int) $validated['microgrid_id'],
            sellerPartyId: $validated['seller_party_id'],
            buyerPartyId: $validated['buyer_party_id'],
            tradedEnergyKwh: (int) $validated['energy_kwh'],
            unitPriceIdrPerKwh: (int) $validated['unit_price_idr'],
            idempotencyKey: $validated['idempotency_key']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi energi P2P berhasil dieksekusi dan dibukukan ke buku besar.',
            'data' => $tradeResult,
        ]);
    }
}
