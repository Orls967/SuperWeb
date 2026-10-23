<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\ManageBanquetEventOrderBomAndMarginAction;
use Modules\Hotel\Application\Actions\MintTimeshareFractionalVillaPointsAction;
use Modules\Hotel\Application\Actions\SettleMultiVendorDestinationPackageAction;
use Modules\Hotel\Domain\Models\BanquetProductionSheet;
use Modules\Hotel\Domain\Models\DestinationPackage;
use Modules\Hotel\Domain\Models\HotelProperty;
use Modules\Hotel\Domain\Models\TimeshareUnit;

class HotelBanquetTimesharePackageController extends Controller
{
    public function banquetOrders(Request $request): View
    {
        $sheets = BanquetProductionSheet::latest()->paginate(15);

        return view('hotel::hotel.banquet_beo', compact('sheets'));
    }

    public function createBanquetOrder(
        Request $request,
        ManageBanquetEventOrderBomAndMarginAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'mice_contract_id' => 'required|string',
            'package_name' => 'required|string|max:128',
            'pax_count' => 'required|integer|min:10',
            'package_price_per_pax_idr' => 'required|integer|min:50000',
            'fnb_materials_cost_idr' => 'required|integer|min:0',
            'av_equipment_vendor_cost_idr' => 'required|integer|min:0',
            'decor_florist_vendor_cost_idr' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['mice_contract_id'],
            $validated['package_name'],
            (int) $validated['pax_count'],
            (int) $validated['package_price_per_pax_idr'],
            (int) $validated['fnb_materials_cost_idr'],
            (int) $validated['av_equipment_vendor_cost_idr'],
            (int) $validated['decor_florist_vendor_cost_idr']
        );

        return redirect()->route('hotel.pms.banquet')
            ->with('success', "Banquet BEO Approved: Margin {$result['gross_margin_pct']}% (Cost: IDR ".number_format($result['total_production_cost_idr']).', Rev: IDR '.number_format($result['total_revenue_idr']).').');
    }

    public function timeshare(Request $request): View
    {
        $properties = HotelProperty::all();
        $units = TimeshareUnit::latest()->paginate(15);

        return view('hotel::hotel.timeshare_fractional', compact('properties', 'units'));
    }

    public function mintTimeshare(
        Request $request,
        MintTimeshareFractionalVillaPointsAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|string',
            'unit_code' => 'required|string|max:32',
            'unit_name' => 'required|string|max:128',
            'daily_rental_rate_idr' => 'required|integer|min:100000',
            'total_token_shares' => 'nullable|integer|min:10|max:1000',
        ]);

        $result = $action->handle(
            $validated['property_id'],
            $validated['unit_code'],
            $validated['unit_name'],
            (int) $validated['daily_rental_rate_idr'],
            (int) ($validated['total_token_shares'] ?? 100)
        );

        return redirect()->route('hotel.pms.timeshare')
            ->with('success', "Timeshare Villa Unit {$result['unit_code']} minted with {$result['total_shares']} fractional syndication shares.");
    }

    public function destinationPackages(Request $request): View
    {
        $packages = DestinationPackage::latest()->paginate(15);

        return view('hotel::hotel.destination_packages', compact('packages'));
    }

    public function settlePackage(
        Request $request,
        SettleMultiVendorDestinationPackageAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'package_code' => 'required|string|max:32',
            'title' => 'required|string|max:128',
            'hotel_amount_idr' => 'required|integer|min:100000',
            'flight_amount_idr' => 'required|integer|min:100000',
            'activity_amount_idr' => 'required|integer|min:100000',
        ]);

        $hotelAmt = (int) $validated['hotel_amount_idr'];
        $flightAmt = (int) $validated['flight_amount_idr'];
        $actAmt = (int) $validated['activity_amount_idr'];
        $totalPriceIdr = $hotelAmt + $flightAmt + $actAmt;

        $vendorShares = [
            ['vendor' => 'HOTEL_RESORT', 'amount' => $hotelAmt, 'account' => 'hotel:escrow'],
            ['vendor' => 'AIRLINE_PARTNER', 'amount' => $flightAmt, 'account' => 'airline:escrow'],
            ['vendor' => 'TOUR_OPERATOR', 'amount' => $actAmt, 'account' => 'tour:escrow'],
        ];

        $result = $action->handle(
            $validated['package_code'],
            $validated['title'],
            $totalPriceIdr,
            $vendorShares
        );

        return redirect()->route('hotel.pms.destination-packages')
            ->with('success', "Multi-Vendor Destination Package {$result['package']->package_code} settled across {$result['vendor_count']} vendors (Total: IDR ".number_format($result['total_price_idr']).').');
    }
}
