<?php

declare(strict_types=1);

namespace Modules\Egy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Egy\Application\Services\MicrogridAndResilienceService;
use Modules\Egy\Domain\Models\BessAsset;

final class EgyBessController extends Controller
{
    public function __construct(
        private readonly MicrogridAndResilienceService $service,
    ) {}

    public function index(): View
    {
        $assets = BessAsset::with('dispatches')->orderBy('id', 'desc')->paginate(15);

        return view('egy::bess.index', compact('assets'));
    }

    public function dispatchArbitrage(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'tariff_profile' => ['required', 'array', 'size:24'],
            'tariff_profile.*' => ['required', 'integer', 'min:100'],
            'dispatch_date' => ['required', 'date_format:Y-m-d'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ]);

        $dispatch = $this->service->dispatchBessArbitrage(
            bessAssetId: $id,
            hourlyTariffProfile: $validated['tariff_profile'],
            dispatchDate: $validated['dispatch_date'],
            idempotencyKey: $validated['idempotency_key']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Jadwal arbitrase BESS berhasil dieksekusi dan dibukukan ke ledger.',
            'data' => $dispatch,
        ]);
    }
}
