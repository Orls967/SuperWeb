<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shared\Domain\Algorithms\Settlement\BundleSspAllocationInput;
use Modules\Venue\Application\Actions\AllocateFestivalBundleSspAction;
use Modules\Venue\Application\Actions\AuditBarShiftStockAction;
use Modules\Venue\Application\Actions\EvaluateMembershipTierAction;
use Modules\Venue\Application\Actions\SettleArtistEngagementAction;
use Modules\Venue\Application\Actions\ValuateSponsorMilestoneAction;
use Modules\Venue\Domain\Algorithms\ArtistDoorSplitInput;
use Modules\Venue\Domain\Algorithms\MembershipTierInput;
use Modules\Venue\Domain\Algorithms\SponsorExposureInput;
use Modules\Venue\Domain\Models\ArtistEngagement;
use Modules\Venue\Domain\Models\BarInventoryItem;
use Modules\Venue\Domain\Models\BarShiftLog;
use Modules\Venue\Domain\Models\EntertainmentEvent;
use Modules\Venue\Domain\Models\EntertainmentVenue;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\FestivalBundleOrder;
use Modules\Venue\Domain\Models\SponsorDeal;
use Modules\Venue\Domain\Models\TableRevpatMetric;
use Modules\Venue\Domain\Models\VenueMembership;

final class VenueEconomyController extends Controller
{
    /**
     * 90.3 Laporan P&L Real-Time Malam & RevPAT Breakdown
     */
    public function pnlReport(Request $request): View
    {
        $venues = EntertainmentVenue::query()->get();
        $selectedVenueId = (int) ($request->query('venue_id') ?? $venues->first()?->id ?? 1);
        $zones = EntertainmentZone::query()->where('venue_id', $selectedVenueId)->get();
        $revpatMetrics = TableRevpatMetric::query()->latest('metric_date')->paginate(15);
        $totalRevpatIdr = (int) TableRevpatMetric::query()->sum('revpat_idr');
        $totalBeverageRevIdr = (int) TableRevpatMetric::query()->sum('beverage_revenue_idr');
        $totalFoodRevIdr = (int) TableRevpatMetric::query()->sum('food_revenue_idr');

        return view('venue::reports.pnl', compact(
            'venues',
            'selectedVenueId',
            'zones',
            'revpatMetrics',
            'totalRevpatIdr',
            'totalBeverageRevIdr',
            'totalFoodRevIdr'
        ));
    }

    /**
     * 90.1 Lembar Penyelesaian Kontrak Artis & Statement
     */
    public function artistStatement(Request $request): View
    {
        $events = EntertainmentEvent::query()->latest()->get();
        $selectedEventId = (int) ($request->query('event_id') ?? $events->first()?->id ?? 1);
        $engagements = ArtistEngagement::query()
            ->with(['event', 'riders'])
            ->where('event_id', $selectedEventId)
            ->latest()
            ->paginate(15);

        return view('venue::artists.statement', compact(
            'events',
            'selectedEventId',
            'engagements'
        ));
    }

    /**
     * 90.4 Portal Anggota VIP & Manajemen Bottle Storage Locker
     */
    public function memberProfile(Request $request): View
    {
        $user = $request->user();
        $membership = VenueMembership::query()->where('user_id', $user?->id ?? 1)->first();
        $allMemberships = VenueMembership::query()->latest()->paginate(15);

        return view('venue::members.profile', compact(
            'user',
            'membership',
            'allMemberships'
        ));
    }

    /**
     * 90.5 Perancang & Kalkulator Alokasi SSP Paket Bundling Festival
     */
    public function festivalBundles(Request $request): View
    {
        $events = EntertainmentEvent::query()->latest()->get();
        $orders = FestivalBundleOrder::query()->with(['event', 'allocations'])->latest()->paginate(15);

        return view('venue::festivals.bundles.create', compact(
            'events',
            'orders'
        ));
    }

    /**
     * 90.7 Dasbor Kemitraan Sponsor & Pengiriman Impresi
     */
    public function sponsorReport(Request $request): View
    {
        $events = EntertainmentEvent::query()->latest()->get();
        $deals = SponsorDeal::query()->with(['event', 'milestones'])->latest()->paginate(15);
        $totalContractValueIdr = (int) SponsorDeal::query()->sum('contract_value_idr');
        $totalDeliveredImpressions = (int) SponsorDeal::query()->sum('delivered_impressions');

        return view('venue::sponsors.report', compact(
            'events',
            'deals',
            'totalContractValueIdr',
            'totalDeliveredImpressions'
        ));
    }

    /**
     * 90.2 Dasbor Inventaris Bar, Spillage, & RevPAT Shift
     */
    public function barShift(Request $request): View
    {
        $venues = EntertainmentVenue::query()->get();
        $selectedVenueId = (int) ($request->query('venue_id') ?? $venues->first()?->id ?? 1);
        $items = BarInventoryItem::query()->where('venue_id', $selectedVenueId)->paginate(15);
        $shiftLogs = BarShiftLog::query()->with('zone')->where('venue_id', $selectedVenueId)->latest()->paginate(15);

        return view('venue::bar.revpat', compact(
            'venues',
            'selectedVenueId',
            'items',
            'shiftLogs'
        ));
    }

    /**
     * Eksekusi Settle Artist Engagement
     */
    public function settleArtist(Request $request, SettleArtistEngagementAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer',
            'artist_name' => 'required|string|max:128',
            'management_company' => 'nullable|string|max:128',
            'deal_type' => 'required|string|in:guarantee,door_split,versus',
            'guarantee_idr' => 'required|integer|min:0',
            'door_gross_idr' => 'required|integer|min:0',
            'approved_expenses_idr' => 'required|integer|min:0',
            'share_bps' => 'required|integer|min:0|max:10000',
            'wht_bps' => 'required|integer|min:0|max:10000',
            'advance_paid_idr' => 'required|integer|min:0',
            'currency' => 'nullable|string|max:10',
            'exchange_rate_idr' => 'nullable|integer|min:1',
        ]);

        $input = new ArtistDoorSplitInput(
            dealType: $validated['deal_type'],
            guaranteeIdr: (int) $validated['guarantee_idr'],
            doorGrossIdr: (int) $validated['door_gross_idr'],
            approvedExpensesIdr: (int) $validated['approved_expenses_idr'],
            shareBps: (int) $validated['share_bps'],
            whtBps: (int) $validated['wht_bps'],
            advancePaidIdr: (int) $validated['advance_paid_idr'],
            currency: $validated['currency'] ?? 'IDR',
            exchangeRateIdr: (int) ($validated['exchange_rate_idr'] ?? 1)
        );

        $actorId = (int) ($request->user()?->id ?? 1);

        $engagement = $action->execute(
            eventId: (int) $validated['event_id'],
            artistName: $validated['artist_name'],
            input: $input,
            actorId: $actorId,
            managementCompany: $validated['management_company'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Artist {$engagement->artist_name} settled successfully.",
                'data' => $engagement,
            ]);
        }

        return redirect()->back()->with('success', "Kontrak Artis {$engagement->artist_name} berhasil diselesaikan. Hak Neto: IDR ".number_format($engagement->net_paid_idr));
    }

    /**
     * Eksekusi Alokasi Festival Bundle SSP
     */
    public function allocateBundle(Request $request, AllocateFestivalBundleSspAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|integer',
            'bundle_code' => 'required|string|max:64',
            'bundle_price_idr' => 'required|integer|min:1',
            'platform_fee_bps' => 'required|integer|min:0|max:10000',
            'components' => 'required|array|min:1',
            'components.*.name' => 'required|string|max:64',
            'components.*.ssp_idr' => 'required|integer|min:1',
            'components.*.vendor_id' => 'required|string|max:64',
        ]);

        $customerId = (int) ($request->user()?->id ?? 1);

        $input = new BundleSspAllocationInput(
            bundlePriceIdr: (int) $validated['bundle_price_idr'],
            platformFeeBps: (int) $validated['platform_fee_bps'],
            components: $validated['components']
        );

        $order = $action->execute(
            eventId: (int) $validated['event_id'],
            bundleCode: $validated['bundle_code'],
            customerId: $customerId,
            input: $input
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Bundle {$order->bundle_code} allocated successfully.",
                'data' => $order,
            ]);
        }

        return redirect()->back()->with('success', "Pesanan Paket Festival {$order->bundle_code} berhasil dibuat dengan alokasi SSP Hamilton-Hare.");
    }

    /**
     * Eksekusi Evaluasi Membership Tier
     */
    public function evaluateMembership(Request $request, EvaluateMembershipTierAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'target_user_id' => 'required|integer',
            'current_tier' => 'required|string|in:sun,moon,infinity',
            'spend_365_days_idr' => 'required|integer|min:0',
            'visits_365_days' => 'required|integer|min:0',
            'is_anniversary_evaluation' => 'nullable|boolean',
            'spend_current_year_idr' => 'nullable|integer|min:0',
            'assigned_locker_number' => 'nullable|string|max:32',
        ]);

        $input = new MembershipTierInput(
            currentTier: $validated['current_tier'],
            spend365DaysIdr: (int) $validated['spend_365_days_idr'],
            visits365Days: (int) $validated['visits_365_days'],
            isAnniversaryEvaluation: (bool) ($validated['is_anniversary_evaluation'] ?? false),
            spendCurrentYearIdr: (int) ($validated['spend_current_year_idr'] ?? 0)
        );

        $membership = $action->execute(
            userId: (int) $validated['target_user_id'],
            input: $input,
            assignedLockerNumber: $validated['assigned_locker_number'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Membership tier evaluated to {$membership->tier}.",
                'data' => $membership,
            ]);
        }

        return redirect()->back()->with('success', "Keanggotaan {$membership->membership_number} berhasil dievaluasi ke tingkat {$membership->tier}.");
    }

    /**
     * Eksekusi Audit Shift Bar
     */
    public function auditBarShift(Request $request, AuditBarShiftStockAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shift_log_id' => 'required|integer',
            'actual_closing_bottles' => 'required|integer|min:0',
            'standard_bottle_volume_ml' => 'nullable|integer|min:1',
        ]);

        $supervisorId = (int) ($request->user()?->id ?? 1);

        $result = $action->execute(
            shiftLogId: (int) $validated['shift_log_id'],
            actualClosingBottles: (int) $validated['actual_closing_bottles'],
            supervisorUserId: $supervisorId,
            standardBottleVolumeMl: (int) ($validated['standard_bottle_volume_ml'] ?? 750)
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Bar shift audit completed.',
                'data' => $result,
            ]);
        }

        return redirect()->back()->with('success', "Audit shift bar selesai. Status: {$result['status']}, Varians: {$result['variance_bottles']} botol.");
    }

    /**
     * Eksekusi Valuasi Milestone Sponsor
     */
    public function valuateSponsorMilestone(Request $request, ValuateSponsorMilestoneAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'milestone_id' => 'required|integer',
            'target_impressions' => 'required|integer|min:1',
            'cpm_rate_idr' => 'required|integer|min:0',
            'milestone_budget_idr' => 'required|integer|min:0',
            'zone_deliveries' => 'required|array|min:1',
            'zone_deliveries.*.zone_name' => 'required|string',
            'zone_deliveries.*.footfall' => 'required|integer|min:0',
            'zone_deliveries.*.dwell_weight_bps' => 'required|integer|min:0',
            'zone_deliveries.*.screen_share_bps' => 'required|integer|min:0',
        ]);

        $input = new SponsorExposureInput(
            zoneDeliveries: $validated['zone_deliveries'],
            targetImpressions: (int) $validated['target_impressions'],
            cpmRateIdr: (int) $validated['cpm_rate_idr'],
            milestoneBudgetAmountIdr: (int) $validated['milestone_budget_idr']
        );

        $result = $action->execute(
            milestoneId: (int) $validated['milestone_id'],
            input: $input
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Sponsor milestone valuated.',
                'data' => $result,
            ]);
        }

        return redirect()->back()->with('success', "Milestone sponsor berhasil divaluasi. Impresi terbobot: {$result['delivered_impressions']}, Rasio Payout: {$result['payout_ratio_bps']} bps.");
    }
}
