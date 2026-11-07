<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlAwardNightPricingRule;

class HotelAwardNightBookingController extends Controller
{
    public function index(Request $request): View
    {
        $rules = HtlAwardNightPricingRule::where('is_active', true)->paginate(10);

        return view('hotel::award_night_dynamic_booking_portal', [
            'rules' => $rules,
        ]);
    }
}
