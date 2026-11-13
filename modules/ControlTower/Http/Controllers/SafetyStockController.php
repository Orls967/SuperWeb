<?php

declare(strict_types=1);

namespace Modules\ControlTower\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Modules\ControlTower\Application\Actions\DecideSafetyStockProposalAction;
use Modules\ControlTower\Application\Services\MultiEchelonSafetyStockService;
use Modules\ControlTower\Domain\Models\SafetyStockPolicy;
use Modules\ControlTower\Http\Requests\SafetyStockProposalRequest;
use Modules\Shared\Application\Ui\CsvExporter;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SafetyStockController extends Controller
{
    public function propose(
        SafetyStockProposalRequest $request,
        MultiEchelonSafetyStockService $service,
    ): JsonResponse|RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();

        $options = [];
        foreach (['demand_history', 'abc_class', 'annual_usage_value_idr', 'total_catalog_value_idr', 'supplier_ref', 'fallback_lead_time_days', 'echelon_weights'] as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                $options[$key] = $data[$key];
            }
        }

        $result = $service->propose(
            itemCode: (string) $data['item_code'],
            proposer: $user,
            options: $options,
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Usulan kebijakan safety stock berhasil diajukan.',
                'data' => [
                    'policy' => $result['policy'],
                    'approval_id' => $result['approval_id'],
                    'echelon_breakdown' => $result['calculation']['echelon_breakdown'],
                ],
            ], 201);
        }

        return redirect()
            ->route('control_tower.safety_stock.index')
            ->with('status', 'Usulan kebijakan safety stock berhasil diajukan.');
    }

    public function decide(
        Request $request,
        SafetyStockPolicy $policy,
        DecideSafetyStockProposalAction $action,
    ): JsonResponse|RedirectResponse {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,approved,reject,rejected'],
            'notes' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $notes = $data['notes'] ?? $data['reason'] ?? null;

        try {
            $updated = $action->execute(
                policy: $policy,
                approver: $user,
                decision: (string) $data['decision'],
                notes: is_string($notes) ? $notes : null,
            );
        } catch (DomainException|InvalidArgumentException|RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->back()->withErrors(['decision' => $e->getMessage()]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Keputusan kebijakan safety stock tercatat.',
                'data' => $updated,
            ]);
        }

        return redirect()
            ->route('control_tower.safety_stock.index')
            ->with('status', 'Keputusan kebijakan safety stock tercatat.');
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $headers = [
            'item_code',
            'abc_class',
            'xyz_class',
            'service_level_bps',
            'safety_stock_qty',
            'reorder_point_qty',
            'status',
            'supplier_ref',
            'lead_time_mean_days',
            'lead_time_sigma_days',
            'proposed_safety_stock_qty',
            'proposed_reorder_point_qty',
        ];

        $rows = SafetyStockPolicy::query()
            ->orderBy('abc_class')
            ->orderBy('item_code')
            ->cursor()
            ->map(static fn (SafetyStockPolicy $policy): array => [
                $policy->item_code,
                $policy->abc_class,
                $policy->xyz_class,
                $policy->service_level_bps,
                $policy->safety_stock_qty,
                $policy->reorder_point_qty,
                $policy->status ?? 'approved',
                $policy->supplier_ref ?? '',
                $policy->lead_time_mean_days ?? '',
                $policy->lead_time_sigma_days ?? '',
                $policy->proposed_safety_stock_qty ?? '',
                $policy->proposed_reorder_point_qty ?? '',
            ]);

        return CsvExporter::streamDownload('control-tower-safety-stock.csv', $headers, $rows);
    }
}
