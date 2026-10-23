<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\RunIdempotentHotelNightAuditAction;
use Modules\Hotel\Application\Actions\SettleSplitGuestFolioCorporatePersonalAction;
use Modules\Hotel\Domain\Models\HotelFolio;
use Modules\Hotel\Domain\Models\HotelProperty;

class HotelNightAuditFolioController extends Controller
{
    public function nightAudit(Request $request): View
    {
        $properties = HotelProperty::all();

        return view('hotel::hotel.night_audit', compact('properties'));
    }

    public function runNightAudit(
        Request $request,
        RunIdempotentHotelNightAuditAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'property_id' => 'required|string',
            'audit_date' => 'required|date',
            'total_available_rooms' => 'nullable|integer|min:1',
        ]);

        $result = $action->handle(
            $validated['property_id'],
            $validated['audit_date'],
            (int) ($validated['total_available_rooms'] ?? 100)
        );

        return redirect()->route('hotel.pms.night-audit')
            ->with('success', "Night Audit Closed for {$result['audit_date']}: {$result['active_in_house_count']} in-house rooms, Total Room Rev: IDR ".number_format($result['total_room_revenue_posted_idr']).', ADR: IDR '.number_format($result['adr_idr']).', RevPAR: IDR '.number_format($result['revpar_idr']).'.');
    }

    public function splitFolios(Request $request): View
    {
        $openFolios = HotelFolio::where('status', 'open')->latest()->paginate(15);

        return view('hotel::hotel.split_folios', compact('openFolios'));
    }

    public function settleSplitFolio(
        Request $request,
        SettleSplitGuestFolioCorporatePersonalAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'folio_id' => 'required|string',
            'corporate_room_share_idr' => 'required|integer|min:0',
            'guest_personal_addon_idr' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            $validated['folio_id'],
            (int) $validated['corporate_room_share_idr'],
            (int) $validated['guest_personal_addon_idr']
        );

        return redirect()->route('hotel.pms.split-folios')
            ->with('success', "Folio {$result['folio']->id} settled! Corporate AR: IDR ".number_format($result['corporate_room_share_idr']).', Guest: IDR '.number_format($result['guest_personal_addon_idr']).'.');
    }
}
