<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Actions\DispatchFlightGateAction;
use Modules\Aviation\Domain\Enums\FlightStatus;
use Modules\Aviation\Domain\Exceptions\AirworthinessCheckFailedException;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Domain\Models\AviationTicket;
use Modules\Aviation\Http\Requests\ExecuteDispatchRequest;
use Modules\Aviation\Ledger\AviationLedgerPostingService;

class AviationDispatchController extends Controller
{
    public function __construct(
        private readonly DispatchFlightGateAction $dispatchAction,
        private readonly AviationLedgerPostingService $ledgerService
    ) {}

    public function index(Request $request): View
    {
        $flights = AviationFlight::with(['aircraft', 'route', 'origin', 'destination', 'weightBalanceRecord'])
            ->whereIn('status', ['planned', 'slot_secured', 'airworthy_cleared', 'boarding'])
            ->orderBy('departure_time')
            ->paginate(15)
            ->withQueryString();

        return view('aviation::dispatch.index', [
            'flights' => $flights,
        ]);
    }

    public function executeDispatch(AviationFlight $flight, ExecuteDispatchRequest $request): JsonResponse
    {
        return $this->execute($flight, $request);
    }

    public function departFlight(AviationFlight $flight, Request $request): JsonResponse
    {
        return $this->depart($flight, $request);
    }

    public function execute(AviationFlight $flight, ExecuteDispatchRequest $request): JsonResponse
    {
        $fuelKg = (int) $request->input('fuel_weight_kg');
        $cargoKg = (int) $request->input('cargo_weight_kg');
        $paxCount = (int) ($request->input('pax_count') ?? 100);

        try {
            $this->dispatchAction->execute($flight, $fuelKg, $cargoKg, $paxCount);

            return response()->json([
                'success' => true,
                'message' => "Penerbangan {$flight->flight_number} berhasil lolos kelaikan A2 dan W&B A3.",
                'flight_id' => $flight->id,
                'status' => $flight->fresh()->status,
            ]);
        } catch (AirworthinessCheckFailedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'AIRWORTHINESS_GATE_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'DISPATCH_ERROR',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function depart(AviationFlight $flight, Request $request): JsonResponse
    {
        $flight->transitionTo(FlightStatus::DEPARTED, 'OFF_BLOCK_DEPARTED');
        $flight->actual_departure = now();
        $flight->save();

        // Pengakuan pendapatan tiket flown
        $baseTotal = (int) AviationTicket::where('flight_id', $flight->id)->whereIn('status', ['issued', 'checked_in', 'boarded'])->sum('base_fare_minor');
        $taxTotal = (int) AviationTicket::where('flight_id', $flight->id)->whereIn('status', ['issued', 'checked_in', 'boarded'])->sum('tax_minor');

        if ($baseTotal > 0) {
            $key = "avi:rev:flight:{$flight->id}:".time();
            $this->ledgerService->postFlightRevenue($flight, $baseTotal, $taxTotal, $key);
        }

        return response()->json([
            'success' => true,
            'message' => "Penerbangan {$flight->flight_number} lepas landas (DEPARTED). Pendapatan diakui ke buku besar.",
            'flight_id' => $flight->id,
            'status' => 'departed',
        ]);
    }
}
