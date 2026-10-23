<?php

declare(strict_types=1);

namespace Modules\Trade\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Trade\Application\Actions\AuditBondedZoneInventoryMovementAction;
use Modules\Trade\Application\Actions\CalculateIncotermsLandedCostAction;
use Modules\Trade\Application\Actions\SubmitCustomsPibPebDeclarationAction;
use Modules\Trade\Domain\Models\ExportOrder;
use Modules\Trade\Domain\Models\ImportOrder;

class TradeCustomsController extends Controller
{
    public function calculateLandedCost(
        Request $request,
        ImportOrder $importOrder,
        CalculateIncotermsLandedCostAction $action
    ): JsonResponse {
        $data = $request->validate([
            'cif_idr' => 'required|integer|min:1',
            'duty_percent' => 'nullable|numeric|min:0',
            'vat_percent' => 'nullable|numeric|min:0',
            'pph22_percent' => 'nullable|numeric|min:0',
        ]);

        $res = $action->execute(
            $importOrder,
            (int) $data['cif_idr'],
            (float) ($data['duty_percent'] ?? 5.0),
            (float) ($data['vat_percent'] ?? 11.0),
            (float) ($data['pph22_percent'] ?? 2.5)
        );

        return response()->json($res);
    }

    public function submitPib(
        ImportOrder $importOrder,
        SubmitCustomsPibPebDeclarationAction $action
    ): JsonResponse {
        $res = $action->execute($importOrder);

        return response()->json($res);
    }

    public function submitPeb(
        ExportOrder $exportOrder,
        SubmitCustomsPibPebDeclarationAction $action
    ): JsonResponse {
        $res = $action->execute($exportOrder);

        return response()->json($res);
    }

    public function auditBondedZone(
        ImportOrder $importOrder,
        AuditBondedZoneInventoryMovementAction $action
    ): JsonResponse {
        $res = $action->execute($importOrder);

        return response()->json($res);
    }
}
