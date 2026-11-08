<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Actions\DispatchPassengerCareAction;
use Modules\Aviation\Actions\ReaccommodatePassengersAction;
use Modules\Aviation\Application\Services\AviationIrropsService;
use Modules\Aviation\Domain\Models\AviationCareVoucher;
use Modules\Aviation\Domain\Models\AviationDisruptionCase;
use Modules\Aviation\Domain\Models\AviationDisruptionPassenger;
use Modules\Aviation\Http\Requests\DispatchCareVoucherRequest;
use Modules\Aviation\Http\Requests\ReaccommodatePassengerRequest;
use Modules\Aviation\Http\Requests\StoreDisruptionCaseRequest;
use Modules\Aviation\Http\ViewModels\IrropsDashboardViewModel;

class AviationIrropsController extends Controller
{
    public function __construct(
        protected AviationIrropsService $irropsService
    ) {}

    public function index(Request $request): View
    {
        $query = AviationDisruptionCase::with(['flight.airline', 'passengers'])
            ->latest('detected_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $cases = $query->paginate(15);

        $statistics = [
            'active_cases' => AviationDisruptionCase::whereNotIn('status', ['closed'])->count(),
            'impacted_passengers' => AviationDisruptionPassenger::count(),
            'vouchers_issued' => AviationCareVoucher::count(),
            'hotel_rooms' => AviationDisruptionPassenger::whereNotNull('hotel_reservation_ref')->count(),
        ];

        $vm = new IrropsDashboardViewModel($cases, $statistics);

        return view('aviation::irrops.index', ['vm' => $vm]);
    }

    public function store(StoreDisruptionCaseRequest $request): RedirectResponse
    {
        $result = $this->irropsService->handleFlightDisruption($request->validated());

        return redirect()->route('aviation.irrops.index')
            ->with('status', sprintf('Kasus IRROPS [%d] berhasil diproses. %d voucher diterbitkan.', $result['disruption_case_id'], $result['vouchers_dispatched']));
    }

    public function dispatchCare(DispatchCareVoucherRequest $request): RedirectResponse
    {
        $caseId = $request->integer('disruption_case_id');
        $dispatchAction = app(DispatchPassengerCareAction::class);
        $res = $dispatchAction->execute($caseId);

        return redirect()->route('aviation.irrops.index')
            ->with('status', sprintf('%d voucher kepedulian berhasil didistribusikan ke penumpang.', $res['vouchers_dispatched']));
    }

    public function reaccommodate(ReaccommodatePassengerRequest $request): RedirectResponse
    {
        $caseId = $request->integer('disruption_case_id');
        $altFlight = $request->has('alternative_flight_id') ? $request->integer('alternative_flight_id') : null;

        $reaccAction = app(ReaccommodatePassengersAction::class);
        $res = $reaccAction->execute($caseId, $altFlight);

        return redirect()->route('aviation.irrops.index')
            ->with('status', sprintf('Re-akomodasi selesai: %d penumpang diproses, %d kamar hotel dialokasikan.', $res['passengers_processed'], $res['hotel_reservations_count']));
    }
}
