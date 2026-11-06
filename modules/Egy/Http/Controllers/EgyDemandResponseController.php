<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\DemandResponseEvent;

final class EgyDemandResponseController extends Controller
{
    public function __construct(
        private readonly MicrogridAndResilienceService $service,
    ) {}

    public function index(): View
    {
        $events = DemandResponseEvent::orderBy('id', 'desc')->paginate(20);

        return view('egy::demand_response.index', compact('events'));
    }

    public function respondCurtailment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_code' => ['required', 'string', 'max:50'],
            'curtailment_signal_id' => ['required', 'string', 'max:60'],
            'start_time' => ['required', 'date'],
            'target_curtailment_kw' => ['required', 'integer', 'min:10'],
            'achieved_curtailment_kw' => ['required', 'integer', 'min:0'],
            'response_time_seconds' => ['required', 'integer', 'min:1'],
        ]);

        $event = $this->service->handleDemandResponseCurtailment(
            eventCode: $validated['event_code'],
            curtailmentSignalId: $validated['curtailment_signal_id'],
            startTime: Carbon::parse($validated['start_time']),
            targetCurtailmentKw: (int) $validated['target_curtailment_kw'],
            achievedCurtailmentKw: (int) $validated['achieved_curtailment_kw'],
            responseTimeSeconds: (int) $validated['response_time_seconds']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Event demand-response berhasil diproses dan insentif terverifikasi.',
            'data' => $event,
        ]);
    }
}
