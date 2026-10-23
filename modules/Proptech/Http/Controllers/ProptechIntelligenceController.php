<?php

declare(strict_types=1);

namespace Modules\Proptech\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Proptech\Application\Actions\BookFlexSpaceHotDeskOrRoomAction;
use Modules\Proptech\Application\Actions\CalculateGreenBuildingRetrofitRoiAction;
use Modules\Proptech\Application\Actions\GenerateSmartLockAccessPinAction;
use Modules\Proptech\Application\Actions\HandoverEpcBimToDigitalTwinAction;
use Modules\Proptech\Application\Actions\OptimizeBmsHvacAndLightingSetpointAction;
use Modules\Proptech\Application\Actions\RunEmergencyEvacuationSimulationAction;
use Modules\Proptech\Domain\Models\Bim\BimModel;
use Modules\Proptech\Domain\Models\BuildingZone;
use Modules\Proptech\Domain\Models\Flex\FlexBooking;
use Modules\Proptech\Domain\Models\Flex\FlexSpace;
use Modules\Proptech\Domain\Models\HvacCommand;
use Modules\Proptech\Domain\Models\PrescriptiveOp;

class ProptechIntelligenceController extends Controller
{
    public function bmsOptimization(Request $request): View
    {
        $zones = BuildingZone::all();
        $commands = HvacCommand::with('zone')->latest()->paginate(15);

        return view('proptech::proptech.bms_optimization', compact('zones', 'commands'));
    }

    public function optimizeBms(
        Request $request,
        OptimizeBmsHvacAndLightingSetpointAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'ambient_temp_c' => 'required|numeric',
            'current_occupancy' => 'required|integer|min:0',
        ]);

        $result = $action->handle(
            (int) $validated['zone_id'],
            (float) $validated['ambient_temp_c'],
            (int) $validated['current_occupancy']
        );

        return redirect()->route('proptech.intelligence.bms')
            ->with('success', "BMS Optimized: Setpoint {$result['target_temp_c']}°C, Mode: {$result['lighting_mode']}, Saved: {$result['estimated_kwh_saved']} kWh (IDR ".number_format($result['savings_idr']).').');
    }

    public function retrofitRoi(Request $request): View
    {
        $zones = BuildingZone::all();
        $proposals = PrescriptiveOp::with('zone')->latest()->paginate(15);

        return view('proptech::proptech.retrofit_roi', compact('zones', 'proposals'));
    }

    public function calcRetrofit(
        Request $request,
        CalculateGreenBuildingRetrofitRoiAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'investment_cost_idr' => 'required|integer|min:1000',
            'expected_kwh_savings_monthly' => 'required|numeric|min:1',
        ]);

        $result = $action->handle(
            (int) $validated['zone_id'],
            (int) $validated['investment_cost_idr'],
            (float) $validated['expected_kwh_savings_monthly']
        );

        return redirect()->route('proptech.intelligence.retrofit')
            ->with('success', "Retrofit Evaluated: Payback in {$result['payback_months']} mos, 5-Yr ROI: {$result['five_year_roi_pct']}%, Offsets {$result['annual_carbon_offset_kg']} kg CO2/yr.");
    }

    public function epcHandover(Request $request): View
    {
        $models = BimModel::with('components')->latest()->paginate(15);

        return view('proptech::proptech.epc_handover', compact('models'));
    }

    public function handoverBim(
        Request $request,
        HandoverEpcBimToDigitalTwinAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'project_code' => 'required|string|max:32',
            'model_name' => 'required|string|max:128',
        ]);

        $components = [
            ['component_code' => 'CHILLER-01-'.$validated['project_code'], 'category' => 'HVAC_CHILLER', 'name' => 'Centrifugal Chiller 400TR'],
            ['component_code' => 'DUCT-MAIN-'.$validated['project_code'], 'category' => 'DUCTWORK', 'name' => 'Main Supply Air Plenum'],
            ['component_code' => 'PUMP-SEC-'.$validated['project_code'], 'category' => 'PIPING', 'name' => 'Secondary Chilled Water Pump'],
        ];

        $result = $action->handle(
            $validated['project_code'],
            $validated['model_name'],
            $components
        );

        return redirect()->route('proptech.intelligence.epc-handover')
            ->with('success', "BIM Handover completed: Model {$result['bim_model']->model_code} (v{$result['version']}) with {$result['components_count']} operational components.");
    }

    public function evacuationSim(Request $request): View
    {
        $zones = BuildingZone::all();

        return view('proptech::proptech.evacuation_sim', compact('zones'));
    }

    public function runSim(
        Request $request,
        RunEmergencyEvacuationSimulationAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'hazard_type' => 'required|string|in:FIRE,GAS_LEAK,EARTHQUAKE',
            'initial_occupancy' => 'required|integer|min:1',
        ]);

        $result = $action->handle(
            (int) $validated['zone_id'],
            $validated['hazard_type'],
            (int) $validated['initial_occupancy']
        );

        return redirect()->route('proptech.intelligence.evacuation')
            ->with('success', "Simulation Finished: Egress {$result['estimated_clearance_sec']}s, Rating: {$result['safety_rating']}, Status: {$result['evacuation_status']}.");
    }

    public function flexBooking(Request $request): View
    {
        $spaces = FlexSpace::all();
        $bookings = FlexBooking::with('space')->latest()->paginate(15);

        return view('proptech::proptech.flex_booking', compact('spaces', 'bookings'));
    }

    public function storeFlexBooking(
        Request $request,
        BookFlexSpaceHotDeskOrRoomAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'flex_space_id' => 'required|integer',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
            'deposit_amount_idr' => 'required|integer|min:0',
        ]);

        $userId = (int) (auth()->id() ?? 1);

        $result = $action->handle(
            (int) $validated['flex_space_id'],
            $userId,
            $validated['start_time'],
            $validated['end_time'],
            (int) $validated['deposit_amount_idr']
        );

        return redirect()->route('proptech.intelligence.flex-booking')
            ->with('success', "Space reserved! Code: {$result['booking']->booking_code}, Total: IDR ".number_format($result['total_price_idr']).'.');
    }

    public function smartLockPins(Request $request): View
    {
        $bookings = FlexBooking::whereIn('status', ['CONFIRMED', 'CHECKED_IN'])->get();

        return view('proptech::proptech.smart_lock_pins', compact('bookings'));
    }

    public function generatePin(
        Request $request,
        GenerateSmartLockAccessPinAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'flex_booking_id' => 'required|integer',
            'passport_hash' => 'required|string|max:64',
        ]);

        $result = $action->handle(
            (int) $validated['flex_booking_id'],
            $validated['passport_hash']
        );

        return redirect()->route('proptech.intelligence.smart-lock')
            ->with('success', "Smart Lock Access PIN generated: {$result['pin_code']} (Relay Token: ".substr($result['door_relay_token'], 0, 16).'...).');
    }
}
