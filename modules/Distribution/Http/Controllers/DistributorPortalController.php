<?php

declare(strict_types=1);

namespace Modules\Distribution\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\Distributor;

/**
 * 42.6 Portal distributor (role `distributor`): katalog harga tier,
 * tagihan, laporan stok/kinerja — tanpa menyentuh domain lain.
 */
class DistributorPortalController extends Controller
{
    public function __construct(private readonly DistributionService $service) {}

    private function currentDistributor(Request $request): Distributor
    {
        $distributor = Distributor::where('owner_user_id', $request->user()->id)->first();

        if ($distributor === null) {
            abort(403, 'Akun ini belum terhubung ke distributor manapun.');
        }

        return $distributor;
    }

    public function home(Request $request): View
    {
        $distributor = $this->currentDistributor($request);

        return view('distribution::portal', [
            'distributor' => $distributor,
            'discount' => $this->service->tierDiscountPercent($distributor->tier),
            'aging' => $this->service->agingReport($distributor),
            'invoices' => $distributor->arInvoices()
                ->orderByDesc('invoice_date')
                ->limit(30)
                ->get(),
            'outlets' => $distributor->outlets()->orderBy('code')->get(),
            'targets' => $distributor->targets()->orderByDesc('period')->get(),
            'scorecards' => $distributor->scorecards()->orderByDesc('period')->get(),
        ]);
    }
}
