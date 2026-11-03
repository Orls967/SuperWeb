<?php

declare(strict_types=1);

namespace Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\Reverse\ProcessPlantIntakeAction;
use Modules\Manufacturing\Domain\Models\RecycledMaterialReceipt;

class RecycledMaterialController extends Controller
{
    public function index(Request $request, ProcessPlantIntakeAction $action): View
    {
        $receipts = RecycledMaterialReceipt::with('materialLot')
            ->orderByDesc('id')
            ->paginate(20);

        $pendingIntakes = $action->getPendingIntakes();

        $stats = [
            'total_receipts' => RecycledMaterialReceipt::count(),
            'total_grams_received' => (int) RecycledMaterialReceipt::sum('quantity_grams'),
            'total_value_idr' => (int) RecycledMaterialReceipt::sum('total_value_idr'),
            'total_emissions_saved_g' => (int) round(RecycledMaterialReceipt::sum('quantity_grams') * 1.8),
            'pending_intakes_count' => $pendingIntakes->count(),
        ];

        return view('manufacturing::recycled_materials.index', [
            'receipts' => $receipts,
            'pendingIntakes' => $pendingIntakes,
            'stats' => $stats,
        ]);
    }

    public function intake(Request $request, ProcessPlantIntakeAction $action): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'shipment_id' => ['required', 'integer', 'exists:lgx_shipments,id'],
            'decision' => ['required', 'string', 'in:accept,reject'],
            'verified_weight_grams' => ['nullable', 'integer', 'min:1'],
            'quality_grade' => ['nullable', 'string', 'in:A,B,C,SCRAP'],
            'notes' => ['nullable', 'string', 'max:500'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $actor = $request->user();
        $shipmentId = (int) $validated['shipment_id'];

        if ($validated['decision'] === 'reject') {
            $reason = $validated['rejection_reason'] ?? 'Ditolak di pabrik';
            $action->reject($shipmentId, $actor, $reason);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'decision' => 'reject',
                    'message' => "Shipment daur ulang ditolak: {$reason}.",
                ]);
            }

            return redirect()->route('manufacturing.recycled-materials.index')
                ->with('success', 'Shipment daur ulang ditolak.');
        }

        $result = $action->execute(
            shipment: $shipmentId,
            netWeightGrams: (int) ($validated['verified_weight_grams'] ?? 0),
            gradeCode: (string) ($validated['quality_grade'] ?? 'A'),
            actor: $actor
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'decision' => 'accept',
                'receipt_id' => $result['receipt_id'],
                'total_value_idr' => $result['total_value_idr'],
                'message' => "Bahan daur ulang berhasil diterima ke gudang manufaktur (Lot #{$result['lot_id']}).",
            ]);
        }

        return redirect()->route('manufacturing.recycled-materials.index')
            ->with('success', 'Bahan daur ulang berhasil diterima ke gudang manufaktur.');
    }
}
