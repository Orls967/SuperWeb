<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Hotel\Domain\Models\HotelCityLedgerAccount;

class HotelCorporatePortalController extends Controller
{
    public function index(Request $request)
    {
        $accounts = HotelCityLedgerAccount::with(['transfers.folio.reservation', 'property'])
            ->where('is_active', true)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'accounts' => $accounts,
            ]);
        }

        return view('hotel::hotel.portal_corporate', compact('accounts'));
    }
}
