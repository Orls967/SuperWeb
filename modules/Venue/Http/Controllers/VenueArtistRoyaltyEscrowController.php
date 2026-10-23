<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Venue\Application\Actions\AccrueCreatorStreamRoyaltyIdempotentAction;
use Modules\Venue\Application\Actions\ExecuteArtistContractEscrowAndWhtPayoutAction;
use Modules\Venue\Application\Actions\ReleaseCreatorHeldRoyaltyAfterWindowAction;
use Modules\Venue\Domain\Models\ArtistContract;
use Modules\Venue\Domain\Models\RightsContract;

class VenueArtistRoyaltyEscrowController extends Controller
{
    public function artistEscrow(Request $request): View
    {
        $contracts = ArtistContract::latest()->paginate(15);

        return view('venue::venue.artist_escrow', compact('contracts'));
    }

    public function settleArtist(
        Request $request,
        ExecuteArtistContractEscrowAndWhtPayoutAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'artist_contract_id' => 'required|string',
            'total_door_sales_idr' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['artist_contract_id'],
            (int) $validated['total_door_sales_idr']
        );

        return redirect()->route('venue.artist.index')
            ->with('success', 'Artist Settled! Gross: IDR '.number_format($result['gross_share_idr']).', WHT: IDR '.number_format($result['wht_tax_idr']).', Net Payout: IDR '.number_format($result['net_payout_idr']).'.');
    }

    public function creatorRoyalties(Request $request): View
    {
        $contracts = RightsContract::with('creator')->latest()->paginate(15);

        return view('venue::venue.creator_royalties', compact('contracts'));
    }

    public function accrueRoyalty(
        Request $request,
        AccrueCreatorStreamRoyaltyIdempotentAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'rights_contract_id' => 'required|integer',
            'stream_count' => 'required|integer|min:1',
            'rate_per_stream_idr' => 'nullable|integer|min:10',
        ]);

        $result = $action->handle(
            (int) $validated['rights_contract_id'],
            (int) $validated['stream_count'],
            (int) ($validated['rate_per_stream_idr'] ?? 150)
        );

        return redirect()->route('venue.artist.royalties')
            ->with('success', 'Royalty Accrued: Creator Share IDR '.number_format($result['creator_share_idr']).', Current Held: IDR '.number_format($result['current_held_idr']).'.');
    }

    public function releaseRoyalties(
        Request $request,
        ReleaseCreatorHeldRoyaltyAfterWindowAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'rights_contract_id' => 'required|integer',
        ]);

        $result = $action->handle((int) $validated['rights_contract_id']);

        return redirect()->route('venue.artist.royalties')
            ->with('success', 'Held Royalties Released! Net Payout: IDR '.number_format($result['net_payout_idr']).' (WHT Tax Deducted: IDR '.number_format($result['wht_tax_idr']).').');
    }
}
