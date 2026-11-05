<?php

declare(strict_types=1);

namespace Modules\Insurance\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\Insurance\Application\Services\CatBondService;
use Modules\Insurance\Domain\Models\CatBond;

final class InvestorCatBondController extends Controller
{
    public function index(Request $request)
    {
        $bonds = CatBond::query()
            ->select(['id', 'bond_code', 'asset_code', 'principal_idr', 'outstanding_principal_idr', 'units', 'spread_bps', 'status', 'issue_date', 'maturity_date'])
            ->orderByDesc('id')
            ->paginate(10);

        return view('insurance::investor.index', compact('bonds'));
    }

    public function show(Request $request, int $id, CatBondService $service)
    {
        $bond = CatBond::query()
            ->select(['id', 'bond_code', 'asset_code', 'principal_idr', 'outstanding_principal_idr', 'units', 'spread_bps', 'attachment_idr', 'exhaustion_idr', 'status', 'issue_date', 'maturity_date'])
            ->findOrFail($id);

        /** @var User $user */
        $user = $request->user();
        $position = $service->positionForHolder($bond, $user->id);

        // Anti-IDOR: investor hanya dapat melihat posisi obligasi jika memiliki unit kepemilikan
        $canBypass = in_array($user->role ?? '', ['admin', 'superadmin', 'reinsurance_mgr'], true);
        if ($position['units'] <= 0 && ! $canBypass) {
            abort(403, 'Akses ditolak: Anda tidak memiliki kepemilikan unit pada Cat Bond ini.');
        }

        return view('insurance::investor.show', [
            'bond' => $bond,
            'units' => $position['units'],
            'currentValueIdr' => $position['current_value_idr'],
            'coupons' => $position['coupons'],
        ]);
    }
}

