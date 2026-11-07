<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Services\BarQueueThroughputOptimizerService;
use Modules\Venue\Application\Services\GuestNpsScorecardAggregatorService;
use Modules\Venue\Application\Services\VenueCrowdAndBiometricService;
use Modules\Venue\Domain\Algorithms\Personalization\PersonalizedOfferContextRouter;
use Modules\Venue\Domain\Models\EntertainmentZone;
use Modules\Venue\Domain\Models\PersonalizedPromo;
use Modules\Venue\Domain\Models\ZoneWpsMetric;

class VenueGuestExperienceController extends Controller
{
    public function __construct(
        private readonly BarQueueThroughputOptimizerService $wpsService,
        private readonly GuestNpsScorecardAggregatorService $npsService,
        private readonly VenueCrowdAndBiometricService $crowdService,
        private readonly PersonalizedOfferContextRouter $offerRouter
    ) {}

    public function wpsDashboard(Request $request): View
    {
        $zoneId = (int) $request->input('zone_id', 1);
        $zone = EntertainmentZone::find($zoneId) ?? EntertainmentZone::first();
        $metrics = ZoneWpsMetric::where('zone_id', $zone?->id ?? 1)->latest('id')->limit(20)->get();
        $overallIndex = $this->wpsService->computeOverallVenueWpsIndex((int) ($zone?->venue_id ?? 1));

        return view('venue::experience.wps', [
            'zone' => $zone,
            'metrics' => $metrics,
            'overallIndex' => $overallIndex,
        ]);
    }

    public function promosDashboard(Request $request): View
    {
        $promos = PersonalizedPromo::where('user_id', auth()->id())->latest('id')->get();
        $allActive = PersonalizedPromo::with(['zone', 'user'])->latest('id')->limit(30)->get();

        return view('venue::experience.promos', [
            'myPromos' => $promos,
            'allActive' => $allActive,
        ]);
    }

    public function dispatchOffer(Request $request): RedirectResponse
    {
        $userId = (int) $request->input('user_id', auth()->id());
        $zoneId = (int) $request->input('zone_id', 1);
        $zone = EntertainmentZone::findOrFail($zoneId);
        $title = (string) $request->input('promo_title', 'VIP Signature Cocktail 20% Off');
        $discount = (int) $request->input('discount_percent', 20);

        $this->crowdService->sendPersonalizedPromo(
            userId: $userId,
            zone: $zone,
            promoTitle: $title,
            discountPercent: $discount
        );

        return redirect()->back()->with('success', 'Penawaran VIP kontekstual berhasil dikirimkan.');
    }

    public function requestBartenderReinforcement(Request $request): RedirectResponse
    {
        $zoneId = (int) $request->input('zone_id', 1);
        $stationNum = (int) $request->input('station_number', 1);

        $this->wpsService->analyzeAndRecordStationWps(
            zoneId: $zoneId,
            stationNumber: $stationNum,
            activeBartenders: 2,
            arrivalRateOrdersPerMinBps: 8000,
            serviceRatePerBartenderBps: 2000
        );

        return redirect()->back()->with('warning', "Sinyal bantuan shift bartender stasiun #{$stationNum} telah dikirim ke modul HCM.");
    }
}
