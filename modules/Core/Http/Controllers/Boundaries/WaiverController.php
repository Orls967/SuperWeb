<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Core\Application\Boundaries\BoundaryOutboxPublisher;
use Modules\Core\Application\Boundaries\WaiverApprovalBridge;
use Modules\Core\Application\Boundaries\WaiveViolationAction;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

final class WaiverController extends Controller
{
    public function __construct(
        private readonly WaiveViolationAction $waiveAction = new WaiveViolationAction,
        private readonly WaiverApprovalBridge $approvalBridge = new WaiverApprovalBridge,
        private readonly BoundaryOutboxPublisher $outboxPublisher = new BoundaryOutboxPublisher,
    ) {}

    public function index(Request $request): View
    {
        $waivers = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value)
            ->orderBy('expires_at')
            ->paginate(30);

        $now = now();
        $activeCount = 0;
        $expiringSoonCount = 0;
        $expiredCount = 0;

        foreach ($waivers as $w) {
            if ($w->expires_at === null || $w->expires_at->isPast()) {
                $expiredCount++;
            } elseif ($now->diffInDays($w->expires_at) <= 7) {
                $expiringSoonCount++;
            } else {
                $activeCount++;
            }
        }

        return view('core::boundaries.waivers', [
            'waivers' => $waivers,
            'summary' => [
                'active' => $activeCount,
                'expiring_soon' => $expiringSoonCount,
                'expired' => $expiredCount,
                'total' => $waivers->total(),
            ],
        ]);
    }

    public function extend(Request $request, int $id): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'adr_anchor' => ['required', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $violation = CoreBoundaryViolation::findOrFail($id);

        $updated = $this->waiveAction->execute(
            $violation,
            (string) ($request->user()?->id ?? 'admin'),
            $validated['reason'],
            $validated['adr_anchor'],
            now()->addDays($validated['days'])
        );

        // Publish outbox event
        $this->outboxPublisher->publishWaiverGranted($updated);

        return redirect()->route('admin.boundaries.waivers')->with('success', 'Waiver berhasil diperbarui.');
    }

    /**
     * Submit an approval request via ApprovalEngine for a proposed waiver.
     */
    public function requestApproval(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'adr_anchor' => ['required', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $violation = CoreBoundaryViolation::findOrFail($id);
        $user = $request->user() ?? User::firstOrFail();

        $expiresAt = now()->addDays($validated['days']);
        $approvalRequest = $this->approvalBridge->requestWaiverApproval(
            $violation,
            $user,
            $validated['adr_anchor'],
            $validated['reason'],
            $expiresAt
        );

        return response()->json([
            'status' => 'SUBMITTED',
            'approval_id' => $approvalRequest?->id,
            'uuid' => $approvalRequest?->uuid,
            'message' => 'Permohonan waiver berhasil diterbitkan ke ApprovalEngine.',
        ]);
    }

    /**
     * Sweep waivers expiring within 7 days and publish inbox tasks.
     */
    public function sweepExpiring(Request $request): RedirectResponse
    {
        $tasks = $this->approvalBridge->sweepExpiringWaivers(7);

        return redirect()->route('admin.boundaries.waivers')->with(
            'success',
            'Pemindaian selesai: diterbitkan '.count($tasks).' tugas peringatan di Kotak Masuk Tugas Saya.'
        );
    }
}
