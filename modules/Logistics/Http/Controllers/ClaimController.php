<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\CreateClaimAction;
use Modules\Logistics\Application\Actions\DecideClaimAction;
use Modules\Logistics\Application\Actions\PayClaimAction;
use Modules\Logistics\Application\Actions\SubmitClaimAction;
use Modules\Logistics\Domain\Models\Claim;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use RuntimeException;

class ClaimController extends Controller
{
    protected function isStaff(Request $request): bool
    {
        $user = $request->user();

        return $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher());
    }

    protected function authorizeAccess(Request $request): void
    {
        abort_unless($this->isStaff($request) || ($request->user() && $request->user()->isShipper()), 403, 'Akses ditolak.');
    }

    public function index(Request $request): View
    {
        $this->authorizeAccess($request);
        $user = $request->user();

        $claims = Claim::with(['shipment', 'creator', 'submitter', 'decider'])
            ->when(! $this->isStaff($request), fn ($q) => $q->whereHas('shipment', fn ($s) => $s->where('shipper_id', $user->id)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('logistics::claims.index', [
            'claims' => $claims,
            'isStaff' => $this->isStaff($request),
            'isApprover' => $user->isAdmin() || $user->isLogisticsAdmin(),
            'types' => Claim::TYPES,
            'status' => $request->query('status'),
        ]);
    }

    public function store(Request $request, CreateClaimAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'tracking_number' => 'required|string|max:32',
            'claim_type' => ['required', Rule::in(Claim::TYPES)],
            'claimed_amount_idr' => 'required|integer|min:1',
            'description' => 'required|string|max:1000',
        ]);

        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($data['tracking_number']))->first();
        if (! $shipment || (! $this->isStaff($request) && $shipment->shipper_id !== $request->user()->id)) {
            return back()->withInput()->with('error', 'Nomor resi tidak ditemukan.');
        }

        try {
            $claim = $action->execute($request->user(), $shipment, $data['claim_type'], (int) $data['claimed_amount_idr'], $data['description']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Draft klaim {$claim->claim_number} dibuat.");
    }

    public function submit(Request $request, int $claim, SubmitClaimAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        try {
            $action->execute($request->user(), Claim::findOrFail($claim));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Klaim diajukan untuk persetujuan.');
    }

    public function decide(Request $request, int $claim, DecideClaimAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'approved_amount_idr' => 'nullable|integer|min:1',
            'notes' => 'required|string|max:500',
        ]);

        try {
            $action->execute(
                $request->user(),
                Claim::findOrFail($claim),
                $data['decision'] === 'approve',
                isset($data['approved_amount_idr']) ? (int) $data['approved_amount_idr'] : null,
                $data['notes']
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Klaim disetujui.' : 'Klaim ditolak.');
    }

    public function pay(Request $request, int $claim, PayClaimAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        try {
            $paid = $action->execute($request->user(), Claim::findOrFail($claim));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Klaim {$paid->claim_number} dibayarkan Rp ".number_format($paid->paid_amount_idr, 0, ',', '.').'.');
    }
}
