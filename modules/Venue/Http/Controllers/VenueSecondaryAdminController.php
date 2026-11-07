<?php

declare(strict_types=1);

namespace Modules\Venue\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Venue\Application\Actions\BlacklistScalperAccountAction;
use Modules\Venue\Application\Services\SecondaryMarketTaxReconciliationService;

final class VenueSecondaryAdminController extends Controller
{
    public function __construct(
        private readonly BlacklistScalperAccountAction $blacklistAction,
        private readonly SecondaryMarketTaxReconciliationService $reconciliationService
    ) {}

    public function blacklist(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|max:500',
            'velocity_requests_per_minute' => 'required|integer|min:0',
            'ip_address' => 'nullable|ip',
            'device_fingerprint' => 'nullable|string|max:255',
        ]);

        $this->blacklistAction->execute(
            (int) $validated['user_id'],
            $validated['reason'],
            (int) $validated['velocity_requests_per_minute'],
            $validated['ip_address'] ?? null,
            $validated['device_fingerprint'] ?? null
        );

        return redirect()->back()->with('status', 'Akun scalper berhasil diblacklist permanen.');
    }

    public function reconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2020|max:2050',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $dto = $this->reconciliationService->reconcilePeriod(
            (int) $validated['year'],
            (int) $validated['month']
        );

        return response()->json($dto->toArray());
    }
}
