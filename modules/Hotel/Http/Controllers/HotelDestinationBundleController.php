<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\PurchaseDestinationPackageAction;
use Modules\Hotel\Application\Actions\ReleaseDestinationPackageMilestoneAction;
use Modules\Hotel\Domain\Models\DestinationPackage;

/**
 * Controller: Pemesanan Paket Destinasi Multi-Vendor & Pelepasan Escrow.
 */
final class HotelDestinationBundleController extends Controller
{
    public function index(): View
    {
        $packages = DestinationPackage::all();

        return view('hotel::destination_package_showcase', [
            'packages' => $packages,
        ]);
    }

    public function book(
        Request $request,
        string $packageId,
        PurchaseDestinationPackageAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'guest_id' => 'required|string',
            'package_price_idr' => 'required|integer|min:1',
            'components' => 'required|array',
            'idempotency_key' => 'required|string',
        ]);

        $res = $action->execute(
            packageId: $packageId,
            guestId: $validated['guest_id'],
            packagePriceIdr: (int) $validated['package_price_idr'],
            components: (array) $validated['components'],
            idempotencyKey: $validated['idempotency_key']
        );

        return response()->json([
            'status' => 'success',
            'data' => $res,
        ]);
    }

    public function releaseMilestone(
        Request $request,
        string $packageId,
        ReleaseDestinationPackageMilestoneAction $action
    ): JsonResponse {
        $validated = $request->validate([
            'vendor_type' => 'required|string',
            'vendor_id' => 'required|string',
            'milestone_amount_idr' => 'required|integer|min:1',
            'milestone_proof_ref' => 'required|string',
            'idempotency_key' => 'required|string',
        ]);

        $res = $action->execute(
            packageId: $packageId,
            vendorType: $validated['vendor_type'],
            vendorId: $validated['vendor_id'],
            milestoneAmountIdr: (int) $validated['milestone_amount_idr'],
            milestoneProofRef: $validated['milestone_proof_ref'],
            idempotencyKey: $validated['idempotency_key']
        );

        return response()->json([
            'status' => 'success',
            'data' => $res,
        ]);
    }
}
