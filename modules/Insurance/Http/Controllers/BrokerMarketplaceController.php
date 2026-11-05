<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Actions\RegisterBrokerAction;
use Modules\Insurance\Application\Actions\SettleBrokerCommissionAction;
use Modules\Insurance\Domain\Models\MarketBroker;
use Modules\Insurance\Domain\Models\MarketCommission;
use Modules\Party\Contracts\Party;

final class BrokerMarketplaceController extends Controller
{
    public function index()
    {
        $brokers = MarketBroker::with('party')->orderByDesc('rating_score')->paginate(15);
        $parties = Party::query()->limit(20)->get();

        return view('insurance::broker.index', compact('brokers', 'parties'));
    }

    public function register(Request $request, RegisterBrokerAction $action)
    {
        $validated = $request->validate([
            'party_id' => 'required|string',
            'license_number' => 'required|string|max:60|unique:ins_market_brokers,license_number',
            'tier' => 'required|in:bronze,silver,gold,platinum',
            'commission_rate_bps' => 'required|integer|min:100|max:3000',
        ]);

        $action->execute(
            partyId: $validated['party_id'],
            licenseNumber: $validated['license_number'],
            tier: $validated['tier'],
            commissionRateBps: (int) $validated['commission_rate_bps'],
        );

        return redirect()->route('insurance.brokers.index')
            ->with('success', 'Broker berhasil didaftarkan di marketplace.');
    }

    public function commissions()
    {
        $commissions = MarketCommission::with(['broker.party', 'policy.product'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('insurance::broker.commissions', compact('commissions'));
    }

    public function settle(int $id, SettleBrokerCommissionAction $action)
    {
        $action->execute($id);

        return redirect()->back()->with('success', "Komisi broker #{$id} berhasil diselesaikan.");
    }
}
