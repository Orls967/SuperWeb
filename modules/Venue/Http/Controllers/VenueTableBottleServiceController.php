<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Venue\Application\Actions\ApplyDynamicBottleSurgePricingAction;
use Modules\Venue\Application\Actions\ReconcileLiquorPourSpoutVarianceAction;
use Modules\Venue\Application\Actions\ReserveTableWithMinimumSpendDepositAction;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTableBooking;

class VenueTableBottleServiceController extends Controller
{
    public function tableBookings(Request $request): View
    {
        $events = EntertainmentEvent::all();
        $bookings = EntertainmentTableBooking::with('event')->latest()->paginate(15);

        return view('venue::venue.vip_tables', compact('events', 'bookings'));
    }

    public function reserveTable(
        Request $request,
        ReserveTableWithMinimumSpendDepositAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'event_id' => 'required|integer',
            'table_number' => 'required|string|max:16',
            'minimum_spend_idr' => 'required|integer|min:1000000',
            'deposit_amount_idr' => 'required|integer|min:500000',
        ]);

        $userId = (int) (auth()->id() ?? 1);

        $result = $action->handle(
            (int) $validated['event_id'],
            $userId,
            $validated['table_number'],
            (int) $validated['minimum_spend_idr'],
            (int) $validated['deposit_amount_idr']
        );

        return redirect()->route('venue.tables.index')
            ->with('success', "VIP Table {$validated['table_number']} reserved! Ref: {$result['booking_code']}, Deposit: IDR ".number_format($result['deposit_amount_idr']).'.');
    }

    public function bottleSurge(Request $request): View
    {
        return view('venue::venue.bottle_surge');
    }

    public function calcSurge(
        Request $request,
        ApplyDynamicBottleSurgePricingAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'bottle_sku' => 'required|string|max:32',
            'base_price_idr' => 'required|integer|min:100000',
            'venue_occupancy_pct' => 'required|numeric|min:0|max:150',
            'hour_of_day' => 'required|integer|min:0|max:23',
        ]);

        $result = $action->handle(
            $validated['bottle_sku'],
            (int) $validated['base_price_idr'],
            (float) $validated['venue_occupancy_pct'],
            (int) $validated['hour_of_day']
        );

        return redirect()->route('venue.tables.surge')
            ->with('success', "Surge Pricing: {$result['surge_tier']} (Multiplier: {$result['surge_multiplier']}x) &rarr; Final Price: IDR ".number_format($result['final_price_idr']));
    }

    public function liquorAudit(Request $request): View
    {
        return view('venue::venue.liquor_audit');
    }

    public function reconcileSpout(
        Request $request,
        ReconcileLiquorPourSpoutVarianceAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'bottle_sku' => 'required|string|max:32',
            'billed_shots' => 'required|integer|min:1',
            'metered_dispensed_ml' => 'required|numeric|min:1',
        ]);

        $result = $action->handle(
            $validated['bottle_sku'],
            (int) $validated['billed_shots'],
            (float) $validated['metered_dispensed_ml']
        );

        $msg = "Spout Reconciled: Variance {$result['variance_ml']} ml ({$result['variance_pct']}%), Status: {$result['status']}.";

        return redirect()->route('venue.tables.liquor-audit')
            ->with($result['is_shrinkage_flagged'] ? 'error' : 'success', $msg);
    }
}
