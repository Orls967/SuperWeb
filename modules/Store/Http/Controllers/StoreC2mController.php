<?php

declare(strict_types=1);

namespace Modules\Store\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Manufacturing\Application\Services\C2m\VmiAndC2mService;

class StoreC2mController extends Controller
{
    public function __construct(
        private readonly VmiAndC2mService $service
    ) {}

    /**
     * D82-2 Halaman Konfigurator C2M untuk pelanggan (role: customer).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $orders = $this->service->getCustomOrdersForCustomer((int) $user->id);

        return view('store::c2m', [
            'orders' => $orders,
            'customer' => $user,
        ]);
    }

    /**
     * D82-2 Validasi konfigurasi parametrik menggunakan CSP (AC-3 / QuickXplain) & PriceLocker.
     */
    public function configure(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_model' => 'required|string|max:64',
            'width' => 'required|integer',
            'depth' => 'required|integer',
            'material' => 'required|string|max:64',
            'drawers' => 'required|integer',
            'raw_material_cost_idr' => 'nullable|integer|min:1000',
        ]);

        $assignments = [
            'width' => (int) $data['width'],
            'depth' => (int) $data['depth'],
            'material' => (string) $data['material'],
            'drawers' => (int) $data['drawers'],
        ];

        $rawMaterialCost = isset($data['raw_material_cost_idr']) ? (int) $data['raw_material_cost_idr'] : 1_500_000;

        $result = $this->service->configureAndQuoteC2mCsp(
            userId: (int) $request->user()->id,
            baseModel: $data['base_model'],
            userAssignments: $assignments,
            rawMaterialCostIdr: $rawMaterialCost
        );

        if (! $result['valid']) {
            return response()->json([
                'error' => 'Konfigurasi tidak konsisten dengan batasan desain produk.',
                'minimal_conflict' => $result['minimal_conflict'],
            ], 422);
        }

        return response()->json([
            'message' => 'Konfigurasi valid dan pesanan C2M berhasil dibuat.',
            'order' => $result['order'],
            'quote_hash' => $result['quote_hash'],
        ], 200);
    }
}
