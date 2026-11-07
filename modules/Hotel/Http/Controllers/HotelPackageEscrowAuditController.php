<?php

declare(strict_types=1);

namespace Modules\Hotel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Hotel\Application\Actions\AuditDestinationPackageEscrowDiscrepancyAction;
use Modules\Hotel\Application\Actions\ReleaseDestinationPackageMilestoneAction;
use Modules\Hotel\Domain\Models\HtlDestinationPackageBooking;

class HotelPackageEscrowAuditController
{
    public function __construct(
        private readonly AuditDestinationPackageEscrowDiscrepancyAction $auditAction,
        private readonly ReleaseDestinationPackageMilestoneAction $releaseAction
    ) {}

    public function index(Request $request): View
    {
        $bookings = HtlDestinationPackageBooking::with('allocations', 'package')->latest()->take(20)->get();

        return view('hotel::hotel_package_escrow_reconciliation', [
            'bookings' => $bookings,
            'totalEscrowHeld' => (int) $bookings->sum('escrow_held_idr'),
        ]);
    }

    public function auditBooking(string $bookingId): JsonResponse
    {
        $report = $this->auditAction->execute($bookingId);

        return response()->json([
            'success' => true,
            'report' => $report,
        ]);
    }

    public function releaseMilestone(Request $request, string $bookingId): JsonResponse
    {
        $validated = $request->validate([
            'vendor_type' => ['required', 'string', 'in:HOTEL,VENUE,LOGISTICS,RESTO'],
        ]);

        $result = $this->releaseAction->execute($bookingId, $validated['vendor_type']);

        return response()->json([
            'success' => true,
            'message' => 'Milestone pencairan dana escrow multi-vendor berhasil dibukukan.',
            'details' => $result,
        ]);
    }
}
