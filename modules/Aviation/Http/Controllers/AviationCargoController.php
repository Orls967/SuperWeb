<?php

declare(strict_types=1);

namespace Modules\Aviation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Aviation\Application\Actions\AcceptAirCargoConsignmentAction;
use Modules\Aviation\Domain\Exceptions\DangerousGoodsRejectedException;
use Modules\Aviation\Domain\Models\AviationCargoConsignment;
use Modules\Aviation\Domain\Models\AviationFlight;
use Modules\Aviation\Http\Requests\ReceiveCargoRequest;

class AviationCargoController extends Controller
{
    public function __construct(
        private readonly AcceptAirCargoConsignmentAction $acceptAction
    ) {}

    public function index(Request $request): View
    {
        $consignments = AviationCargoConsignment::with(['flight.route', 'flight.origin', 'flight.destination'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $flights = AviationFlight::whereIn('status', ['planned', 'slot_secured', 'airworthy_cleared'])->limit(20)->get();

        return view('aviation::cargo.index', [
            'consignments' => $consignments,
            'flights' => $flights,
        ]);
    }

    public function receiveCargo(ReceiveCargoRequest $request): JsonResponse
    {
        return $this->receive($request);
    }

    public function receive(ReceiveCargoRequest $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        $payload = array_merge($request->validated(), ['idempotency_key' => $idempotencyKey]);

        try {
            $consignment = $this->acceptAction->execute($payload);

            return response()->json([
                'success' => true,
                'message' => "Kargo AWB {$consignment->consignment_number} berhasil diterima dan dicatat.",
                'consignment_id' => $consignment->id,
                'consignment_number' => $consignment->consignment_number,
                'custody_hash' => $consignment->custody_hash_chain,
            ], 200);
        } catch (DangerousGoodsRejectedException $e) {
            return response()->json([
                'success' => false,
                'error' => 'DANGEROUS_GOODS_REJECTED',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'CARGO_ACCEPTANCE_FAILED',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
