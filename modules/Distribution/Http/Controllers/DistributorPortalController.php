<?php

declare(strict_types=1);

namespace Modules\Distribution\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Domain\Models\AuditLog;
use Modules\Distribution\Application\Services\DistributionService;
use Modules\Distribution\Domain\Models\Distributor;

/**
 * 42.6 Portal distributor (role `distributor` dan `admin` untuk supervisi):
 * katalog harga tier, tagihan, laporan stok/kinerja.
 * Admin diarahkan ke selector distributor jika tanpa query ?distributor=<id>.
 */
class DistributorPortalController extends Controller
{
    public function __construct(private readonly DistributionService $service) {}

    public function home(Request $request): View
    {
        $user = $request->user();

        // Non-admin dilarang memanipulasi query parameter ?distributor
        if ($request->has('distributor') && ! $user->isAdmin()) {
            abort(403, 'Hanya administrator yang dapat memilih distributor spesifik.');
        }

        // Alur Administrator
        if ($user->isAdmin()) {
            $distributorId = $request->query('distributor');

            // Default admin tanpa query ?distributor: halaman selector distributor (D3)
            if (! $distributorId) {
                $distributors = Distributor::query()
                    ->orderBy('code')
                    ->get();

                return view('distribution::selector', [
                    'distributors' => $distributors,
                ]);
            }

            // Admin dengan query ?distributor=<id>
            $distributor = Distributor::find($distributorId);
            if ($distributor === null) {
                abort(404, 'Distributor tidak ditemukan.');
            }

            // Catat akses admin ke audit log (D3 / A5)
            AuditLog::record(
                action: 'distribution.portal.admin_view',
                auditable: $distributor,
                context: [
                    'admin_user_id' => $user->id,
                    'distributor_id' => $distributor->id,
                    'distributor_code' => $distributor->code,
                ],
                impactType: 'security'
            );

            return $this->renderPortalView($distributor);
        }

        // Alur User Distributor
        $distributor = Distributor::where('owner_user_id', $user->id)->first();
        if ($distributor === null) {
            abort(403, 'Akun ini belum terhubung ke distributor manapun.');
        }

        return $this->renderPortalView($distributor);
    }

    private function renderPortalView(Distributor $distributor): View
    {
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
