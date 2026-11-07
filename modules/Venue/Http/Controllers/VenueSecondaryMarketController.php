<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Venue\Application\Actions\AllocateDynamicBundleAction;
use Modules\Venue\Application\Actions\ClaimWaitlistSeatOfferAction;
use Modules\Venue\Application\Actions\ListTicketForResaleAction;
use Modules\Venue\Domain\Models\DynamicBundlePackage;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentTicket;
use Modules\Venue\Domain\Models\ScalperBlacklistLog;
use Modules\Venue\Domain\Models\TicketResale;
use Modules\Venue\Domain\Models\TicketWaitlist;

class VenueSecondaryMarketController extends Controller
{
    /**
     * Marketplace Tiket Resmi Sekunder (P2P verified, anti-scalping max 120%)
     */
    public function marketplace(Request $request): View
    {
        $listings = TicketResale::query()
            ->with(['ticket.event', 'seller'])
            ->where('status', 'LISTED')
            ->latest()
            ->paginate(12);

        $bundles = DynamicBundlePackage::query()
            ->with(['event', 'components'])
            ->where('status', 'PUBLISHED')
            ->latest()
            ->take(6)
            ->get();

        return view('venue::resale.marketplace', [
            'listings' => $listings,
            'bundles' => $bundles,
            'totalListings' => TicketResale::query()->where('status', 'LISTED')->count(),
            'settledToday' => TicketResale::query()->where('status', 'SETTLED')->whereDate('settled_at', today())->count(),
        ]);
    }

    /**
     * Formulir Penjualan Tiket Sah (Capped at 120%, Single Transfer Rule)
     */
    public function sell(Request $request): View
    {
        $user = $request->user();
        $userTickets = EntertainmentTicket::query()
            ->with('event')
            ->where('buyer_user_id', $user?->id)
            ->whereIn('status', ['VALID', 'ISSUED'])
            ->where('transfer_count', '<', 1)
            ->get();

        return view('venue::resale.sell', [
            'tickets' => $userTickets,
            'maxPriceMultiplier' => 1.20,
        ]);
    }

    /**
     * Daftarkan tiket ke pasar sekunder
     */
    public function storeListing(Request $request, ListTicketForResaleAction $action)
    {
        $request->validate([
            'ticket_id' => 'required|integer',
            'asking_price_idr' => 'required|integer|min:1000',
        ]);

        $ticket = EntertainmentTicket::query()->findOrFail($request->integer('ticket_id'));

        $resale = $action->execute(
            ticket: $ticket,
            sellerUserId: (int) $request->user()->id,
            askingPriceIdr: $request->integer('asking_price_idr')
        );

        return redirect()->route('venue.secondary.orders')
            ->with('success', sprintf('Tiket #%s berhasil didaftarkan ke marketplace dengan kode %s', $ticket->ticket_number, $resale->resale_code));
    }

    /**
     * Dashboard Riwayat Pesanan & Tiket Sekunder
     */
    public function orders(Request $request): View
    {
        $user = $request->user();

        $mySales = TicketResale::query()
            ->with(['ticket.event'])
            ->where('seller_user_id', $user?->id)
            ->latest()
            ->get();

        $myPurchases = TicketResale::query()
            ->with(['ticket.event'])
            ->where('buyer_user_id', $user?->id)
            ->latest()
            ->get();

        return view('venue::resale.orders', [
            'sales' => $mySales,
            'purchases' => $myPurchases,
        ]);
    }

    /**
     * Dynamic Bundle Package Creator & Inventory Browser
     */
    public function bundles(Request $request): View
    {
        $events = EntertainmentEvent::query()->where('status', 'ON_SALE')->get();
        $bundles = DynamicBundlePackage::query()->with(['event', 'components'])->latest()->paginate(10);

        return view('venue::bundles.dynamic-builder', [
            'events' => $events,
            'bundles' => $bundles,
        ]);
    }

    /**
     * Simpan paket bundle baru
     */
    public function storeBundle(Request $request, AllocateDynamicBundleAction $action)
    {
        $validated = $request->validate([
            'event_id' => 'required|integer',
            'package_name' => 'required|string|max:150',
            'occupancy_percentage' => 'nullable|integer|min:0|max:100',
            'lead_time_days' => 'nullable|integer|min:0',
            'components' => 'required|array|min:1',
        ]);

        $package = $action->execute(
            eventId: (int) $validated['event_id'],
            packageName: (string) $validated['package_name'],
            components: (array) $validated['components'],
            occupancyPercentage: (int) ($validated['occupancy_percentage'] ?? 80),
            leadTimeDays: (int) ($validated['lead_time_days'] ?? 7)
        );

        return redirect()->route('venue.secondary.bundles')
            ->with('success', sprintf('Paket Dynamic Bundle %s berhasil dipublikasikan (Final Price: Rp %s)', $package->package_name, number_format($package->final_price_idr, 0, ',', '.')));
    }

    /**
     * Antrean Waitlist & Pelepasan Kursi Bertenggat Waktu 15 Menit
     */
    public function waitlist(Request $request): View
    {
        $user = $request->user();
        $activeWaitlists = TicketWaitlist::query()
            ->with(['event', 'zone'])
            ->where('user_id', $user?->id)
            ->latest()
            ->get();

        $pendingOffers = TicketWaitlist::query()
            ->with(['event', 'zone', 'reservations'])
            ->where('status', 'OFFERED')
            ->where('offer_expires_at', '>', now())
            ->latest()
            ->get();

        return view('venue::waitlist.queue', [
            'myWaitlists' => $activeWaitlists,
            'pendingOffers' => $pendingOffers,
        ]);
    }

    /**
     * Klaim tawaran kursi waitlist
     */
    public function claimWaitlist(Request $request, ClaimWaitlistSeatOfferAction $action)
    {
        $request->validate([
            'waitlist_id' => 'required|integer',
        ]);

        $waitlist = TicketWaitlist::query()->findOrFail($request->integer('waitlist_id'));
        $claimed = $action->execute($waitlist);

        return redirect()->route('venue.secondary.waitlist')
            ->with('success', sprintf('Tawaran kursi #%s berhasil diklaim sebelum tenggat 15 menit berakhir!', $claimed->waitlist_code));
    }

    /**
     * Laporan Analitik Pasar Sekunder, Pajak PBJT & Log Bot Scalper
     */
    public function reports(Request $request): View
    {
        $settledResales = TicketResale::query()->where('status', 'SETTLED')->get();

        $totalVolumeIdr = $settledResales->sum('resale_price_idr');
        $totalPbjtIdr = $settledResales->sum('tax_pbjt_idr');
        $totalPlatformFeeIdr = $settledResales->sum('platform_fee_idr');
        $totalRoyaltyIdr = $settledResales->sum('promoter_royalty_idr');
        $totalSellerPayoutIdr = $settledResales->sum('seller_payout_idr');

        $scalperLogs = ScalperBlacklistLog::query()->latest()->take(15)->get();

        return view('venue::reports.secondary-economics', [
            'totalVolumeIdr' => $totalVolumeIdr,
            'totalPbjtIdr' => $totalPbjtIdr,
            'totalPlatformFeeIdr' => $totalPlatformFeeIdr,
            'totalRoyaltyIdr' => $totalRoyaltyIdr,
            'totalSellerPayoutIdr' => $totalSellerPayoutIdr,
            'settledCount' => $settledResales->count(),
            'scalperLogs' => $scalperLogs,
        ]);
    }
}
