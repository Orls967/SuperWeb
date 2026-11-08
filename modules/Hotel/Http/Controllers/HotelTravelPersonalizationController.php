<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlPersonalizedTravelCampaign;

class HotelTravelPersonalizationController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = HtlPersonalizedTravelCampaign::latest()->paginate(10);

        return view('hotel::travel_personalization_recommendation_board', [
            'campaigns' => $campaigns,
        ]);
    }
}
