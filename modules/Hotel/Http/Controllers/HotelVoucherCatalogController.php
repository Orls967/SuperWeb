<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Hotel\Domain\Services\HotelVoucherCatalogRedemptionService;

final class HotelVoucherCatalogController extends Controller
{
    public function __construct(
        protected HotelVoucherCatalogRedemptionService $voucherService
    ) {}

    public function redeem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'travel_pass_id' => 'required|string',
            'catalog_item_code' => 'required|string',
            'catalog_item_name' => 'required|string',
            'category' => 'nullable|string',
            'points_required' => 'required|integer|min:500',
            'face_value_idr' => 'required|integer|min:50000',
        ]);

        $result = $this->voucherService->redeemCatalogVoucher(
            travelPassId: $validated['travel_pass_id'],
            catalogItemCode: $validated['catalog_item_code'],
            catalogItemName: $validated['catalog_item_name'],
            category: $validated['category'] ?? 'FNB_DINING',
            pointsRequired: (int) $validated['points_required'],
            faceValueIdr: (int) $validated['face_value_idr']
        );

        return redirect()->back()->with('success', "Penukaran voucher berhasil! Kode Voucher: {$result['voucher_code']}");
    }
}
