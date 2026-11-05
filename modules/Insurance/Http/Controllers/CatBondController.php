<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\CatBondService;
use Modules\Insurance\Domain\Models\CatBond;

final class CatBondController extends Controller
{
    public function index(Request $request)
    {
        $bonds = CatBond::with(['coupons', 'triggerEvents'])->orderByDesc('id')->paginate(10);

        return view('insurance::cat_bonds.index', compact('bonds'));
    }

    public function show(int $id)
    {
        $bond = CatBond::with(['coupons.payouts', 'triggerEvents'])->findOrFail($id);

        return view('insurance::cat_bonds.show', compact('bond'));
    }

    public function store(Request $request, CatBondService $service)
    {
        $validated = $request->validate([
            'bond_code' => ['required', 'string', 'max:50'],
            'asset_code' => ['required', 'string', 'max:50'],
            'principal_idr' => ['required', 'integer', 'min:1000000'],
            'units' => ['required', 'integer', 'min:1'],
            'spread_bps' => ['required', 'integer', 'min:1', 'max:5000'],
            'attachment_idr' => ['required', 'integer', 'min:0'],
            'exhaustion_idr' => ['required', 'integer', 'min:0'],
            'issue_date' => ['required', 'date'],
            'maturity_date' => ['required', 'date'],
        ]);

        $key = $request->header('Idempotency-Key') ?? (string) $request->input('idempotency_key', 'bond-issue-'.$validated['bond_code']);

        /** @var User $user */
        $user = $request->user();
        $bond = $service->issueBond($validated, $key, $user->id);

        return redirect()->route('insurance.cat-bonds.show', $bond->id)
            ->with('status', "Cat bond {$bond->bond_code} berhasil diterbitkan.");
    }

    public function distributeCoupon(Request $request, int $id, CatBondService $service)
    {
        $bond = CatBond::findOrFail($id);
        $validated = $request->validate([
            'quarter_key' => ['required', 'regex:/^\d{4}-Q[1-4]$/'],
        ]);

        $key = $request->header('Idempotency-Key') ?? (string) $request->input('idempotency_key', "coupon-{$bond->bond_code}-{$validated['quarter_key']}");
        $coupon = $service->distributeQuarterlyCoupon($bond, $validated['quarter_key'], $key);

        return redirect()->route('insurance.cat-bonds.show', $bond->id)
            ->with('status', "Kupon kuartal {$validated['quarter_key']} sebesar Rp ".number_format($coupon->total_idr, 0, ',', '.').' berhasil dibagikan.');
    }

    public function trigger(Request $request, int $id, CatBondService $service)
    {
        $bond = CatBond::findOrFail($id);
        $validated = $request->validate([
            'industry_loss_idr' => ['required', 'integer', 'min:1'],
            'event_key' => ['required', 'string', 'max:100'],
        ]);

        $key = $request->header('Idempotency-Key') ?? (string) $request->input('idempotency_key', "trig-{$bond->bond_code}-{$validated['event_key']}");
        $event = $service->triggerIndemnity($bond, (int) $validated['industry_loss_idr'], $validated['event_key'], $key);

        return redirect()->route('insurance.cat-bonds.show', $bond->id)
            ->with('status', 'Trigger bencana diaplikasikan. Reduksi pokok: Rp '.number_format($event->reduction_idr, 0, ',', '.'));
    }
}
