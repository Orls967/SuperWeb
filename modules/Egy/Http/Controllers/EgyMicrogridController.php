<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\Microgrid;

final class EgyMicrogridController extends Controller
{
    public function __construct(
        private readonly MicrogridAndResilienceService $service,
    ) {}

    public function index(): View
    {
        $microgrids = Microgrid::with('feeders')->orderBy('id', 'desc')->paginate(15);

        return view('egy::microgrid.index', compact('microgrids'));
    }

    public function triggerIsland(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'available_generation_kw' => ['required', 'integer', 'min:0'],
        ]);

        $result = $this->service->triggerIslanding($id, (int) $validated['available_generation_kw']);

        return response()->json([
            'status' => 'success',
            'message' => 'Transisi islanding berhasil dieksekusi dengan pelepasan beban deterministik.',
            'data' => [
                'total_served_kw' => $result->totalServedKw,
                'total_shed_kw' => $result->totalShedKw,
                'retained_feeders' => $result->retainedFeeders,
                'shed_feeders' => $result->shedFeeders,
                'certificate_hash' => $result->certificateHash,
            ],
        ]);
    }
}
