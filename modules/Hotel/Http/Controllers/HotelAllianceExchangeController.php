<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlAlliancePartner;
use Modules\Hotel\Domain\Models\HtlAllianceTransferLog;

class HotelAllianceExchangeController extends Controller
{
    public function index(Request $request): View
    {
        $partners = HtlAlliancePartner::where('is_active', true)->get();
        $recentTransfers = HtlAllianceTransferLog::latest()->take(10)->get();

        return view('hotel::partner_point_exchange_hub', [
            'partners' => $partners,
            'recentTransfers' => $recentTransfers,
        ]);
    }
}
