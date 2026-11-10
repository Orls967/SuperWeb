<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Contracts\DigitalTwinInterface;
use Modules\Core\Domain\Models\SimTwinSnapshot;
use Modules\Core\Domain\Models\SimTwinState;

class SimulationTwinController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected DigitalTwinInterface $twinService,
    ) {}

    public function index(Request $request): View|JsonResponse
    {
        $this->authorize('viewAny', SimTwinState::class);

        $query = SimTwinState::query()->orderBy('id', 'desc');

        if ($request->filled('entity_type')) {
            $query->where('entity_type', (string) $request->query('entity_type'));
        }

        if ($request->filled('entity_id')) {
            $query->where('entity_id', (string) $request->query('entity_id'));
        }

        if ($request->has('sandbox')) {
            $query->where('is_sandbox', $request->boolean('sandbox'));
        }

        $twins = $query->paginate(20)->withQueryString();
        $types = SimTwinState::select('entity_type')->distinct()->pluck('entity_type');

        if ($request->wantsJson()) {
            return response()->json([
                'data' => $twins->items(),
                'types' => $types,
                'total' => $twins->total(),
            ]);
        }

        return view('core::admin.simulation.twins', [
            'twins' => $twins,
            'types' => $types,
            'filters' => $request->only(['entity_type', 'entity_id', 'sandbox']),
        ]);
    }

    public function show(Request $request, string $type, string $id): View|JsonResponse
    {
        $this->authorize('viewAny', SimTwinState::class);

        $history = SimTwinState::where('entity_type', $type)
            ->where('entity_id', $id)
            ->orderBy('id', 'asc')
            ->get();

        $snapshots = SimTwinSnapshot::where('entity_type', $type)
            ->where('entity_id', $id)
            ->orderBy('day_number', 'asc')
            ->get();

        $isChainValid = $this->twinService->verifyChain($type, $id);

        if ($request->wantsJson()) {
            return response()->json([
                'entity_type' => $type,
                'entity_id' => $id,
                'chain_valid' => $isChainValid,
                'history' => $history,
                'snapshots' => $snapshots,
            ]);
        }

        return view('core::admin.simulation.twin_detail', [
            'entityType' => $type,
            'entityId' => $id,
            'isChainValid' => $isChainValid,
            'history' => $history,
            'snapshots' => $snapshots,
        ]);
    }
}
