<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers\Boundaries;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Application\Boundaries\BoundaryOutboxPublisher;
use Modules\Core\Application\Boundaries\WaiverApprovalBridge;
use Modules\Core\Application\Boundaries\WaiveViolationAction;
use Modules\Core\Contracts\ClockInterface;
use Modules\Core\Domain\Enums\ViolationStatus;
use Modules\Core\Domain\Models\CoreBoundaryViolation;

final class WaiverController extends Controller
{
    public function __construct(
        private readonly WaiveViolationAction $waiveAction = new WaiveViolationAction,
        private readonly WaiverApprovalBridge $approvalBridge = new WaiverApprovalBridge,
        private readonly BoundaryOutboxPublisher $outboxPublisher = new BoundaryOutboxPublisher,
        private readonly ?ClockInterface $clock = null,
    ) {}

    public function index(Request $request): View
    {
        $sortCol = in_array($request->string('sort')->toString(), ['id', 'rule_id', 'client_module', 'expires_at'], true)
            ? $request->string('sort')->toString()
            : 'expires_at';
        $sortDir = strtolower($request->string('dir')->toString()) === 'desc' ? 'desc' : 'asc';

        $query = CoreBoundaryViolation::where('status', ViolationStatus::WAIVED->value);
        if ($request->filled('module')) {
            $module = $request->string('module')->toString();
            $query->where(fn ($q) => $q->where('client_module', $module)->orWhere('owner_module', $module));
        }

        $waivers = $query->orderBy($sortCol, $sortDir)->paginate(30)->withQueryString();

        $now = ($this->clock ?? app(ClockInterface::class))->now();
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
            'sortCol' => $sortCol,
            'sortDir' => $sortDir,
            'now' => $now,
            'summary' => [
                'active' => $activeCount,
                'expiring_soon' => $expiringSoonCount,
                'expired' => $expiredCount,
                'total' => $waivers->total(),
            ],
        ]);
    }

    public function extend(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $idempotencyKey = trim((string) ($request->header('Idempotency-Key') ?: $request->input('idempotency_key', '')));
        if ($idempotencyKey === '') {
            return response()->json([
                'message' => 'Header atau field Idempotency-Key wajib disertakan untuk perpanjangan waiver.',
            ], 422);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'adr_anchor' => ['required', 'string', 'max:255'],
            'days' => ['required', 'integer', 'min:1', 'max:90'],
        ]);

        $payloadHash = hash('sha256', (string) json_encode([
            'violation_id' => $id,
            'reason' => $validated['reason'],
            'adr_anchor' => $validated['adr_anchor'],
            'days' => (int) $validated['days'],
        ]));

        $cacheKey = 'idemp:boundary:waiver:extend:'.sha1($id.':'.$idempotencyKey);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            if (($cached['payload_hash'] ?? '') !== $payloadHash) {
                return response()->json([
                    'message' => 'Idempotency conflict: payload berbeda untuk Idempotency-Key yang sama.',
                ], 409);
            }

            if ($request->wantsJson()) {
                return response()->json(['status' => 'replayed', 'violation_id' => $id], 200);
            }

            return redirect()->route('admin.architecture.waivers')->with('success', 'Waiver berhasil diperbarui (idempoten).');
        }

        $violation = CoreBoundaryViolation::findOrFail($id);
        $actorId = (string) ($request->user()?->id ?? 'admin');
        $now = ($this->clock ?? app(ClockInterface::class))->now();

        $updated = $this->waiveAction->extend(
            $violation,
            $actorId,
            $validated['reason'],
            $validated['adr_anchor'],
            (int) $validated['days']
        );

        $this->outboxPublisher->publishWaiverGranted($updated);
        Cache::put($cacheKey, ['payload_hash' => $payloadHash], $now->copy()->addHours(24));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'extended', 'violation_id' => $updated->id], 200);
        }

        return redirect()->route('admin.architecture.waivers')->with('success', 'Waiver berhasil diperbarui.');
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

        $now = ($this->clock ?? app(ClockInterface::class))->now();
        $expiresAt = $now->copy()->addDays((int) $validated['days']);
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
