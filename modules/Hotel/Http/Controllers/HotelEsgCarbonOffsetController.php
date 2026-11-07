<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Domain\Models\HtlTravelPass;
use Modules\Hotel\Domain\Services\HotelEsgCarbonOffsetDonationService;

final class HotelEsgCarbonOffsetController extends Controller
{
    public function __construct(
        protected HotelEsgCarbonOffsetDonationService $donationService
    ) {}

    public function index(Request $request): View
    {
        $passes = HtlTravelPass::where('is_active', true)->limit(20)->get();

        return view('hotel::hotel_esg_carbon_offset_portal', [
            'passes' => $passes,
        ]);
    }

    public function donate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'travel_pass_id' => 'required|string',
            'points_to_donate' => 'required|integer|min:100',
            'esg_program_code' => 'required|string',
            'tree_planting_location' => 'nullable|string',
        ]);

        $result = $this->donationService->donatePointsForCarbonOffset(
            travelPassId: $validated['travel_pass_id'],
            pointsToDonate: (int) $validated['points_to_donate'],
            esgProgramCode: $validated['esg_program_code'],
            treePlantingLocation: $validated['tree_planting_location'] ?? 'Taman Hutan Raya Ngurah Rai Bali'
        );

        return redirect()->back()->with('success', "Donasi berhasil! Sertifikat {$result['certificate_id']} terbit ({$result['kg_co2_offset']} kg CO2 diserap).");
    }
}
