<?php

declare(strict_types=1);

namespace Modules\Treasury\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Treasury\Application\Actions\DrawFacilityAction;
use Modules\Treasury\Domain\Models\CreditFacility;
use Modules\Treasury\Http\Requests\DrawCreditFacilityRequest;

final class CreditFacilityController extends Controller
{
    public function __construct(
        protected readonly DrawFacilityAction $drawAction
    ) {}

    public function index(Request $request): JsonResponse
    {
        $facilities = CreditFacility::query()
            ->where('status', 'active')
            ->orderBy('bank_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'count' => $facilities->count(),
            'facilities' => $facilities,
        ]);
    }

    public function show(CreditFacility $facility): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'facility' => $facility->load('drawdowns'),
        ]);
    }

    public function draw(DrawCreditFacilityRequest $request, CreditFacility $facility): JsonResponse
    {
        $result = $this->drawAction->execute(
            facility: $facility,
            amountIdr: $request->getAmount(),
            currentDerPermille: $request->getCurrentDerPermille(),
            idempotencyKey: $request->header('Idempotency-Key'),
            userId: $request->user()?->id ?? 1
        );

        return response()->json([
            'status' => 'drawn',
            'facility' => $facility->fresh(),
            'result' => $result,
        ]);
    }
}
