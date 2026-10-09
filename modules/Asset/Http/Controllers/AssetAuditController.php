<?php

declare(strict_types=1);

namespace Modules\Asset\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Asset\Application\Services\AssetAuditService;
use Modules\Asset\Application\Services\AssetTcoService;
use Modules\Asset\Application\Services\DepreciationService;
use Modules\Asset\Domain\Enums\AssetStatus;
use Modules\Asset\Domain\Models\Asset;
use Modules\Asset\Domain\Models\AssetDisposal;
use Modules\Asset\Domain\Models\AssetLease;
use Modules\Asset\Domain\Models\AssetRevaluation;
use Modules\Asset\Domain\Models\AssetWorkOrder;
use Modules\Asset\Domain\Models\Depreciation;

/**
 * Laporan & operasi penyusutan massal (31.1, 31.2, 31.7, 31.8).
 */
class AssetAuditController extends Controller
{
    public function __construct(
        private readonly AssetAuditService $audit,
        private readonly AssetTcoService $tco,
        private readonly DepreciationService $depreciation,
    ) {}

    public function index(): View
    {
        $result = $this->audit->audit();

        $assets = Asset::query()
            ->where('status', '!=', AssetStatus::Disposed)
            ->with(['category', 'location'])
            ->get();

        $tcoRows = $assets->map(fn (Asset $asset): array => $this->tco->tco($asset));

        return view('asset::audit', [
            'result' => $result,
            'tcoRows' => $tcoRows,
            'assets' => $assets,
            'periods' => Depreciation::query()->select('period')->distinct()->orderByDesc('period')->pluck('period'),
            'counts' => [
                'assets' => $assets->count(),
                'work_orders_open' => AssetWorkOrder::whereIn('status', ['scheduled', 'in_progress'])->count(),
                'leases_active' => AssetLease::where('status', 'active')->count(),
                'revaluations_pending' => AssetRevaluation::where('approval_status', 'pending')->count(),
                'disposals_pending' => AssetDisposal::where('approval_status', 'pending')->count(),
            ],
        ]);
    }

    public function depreciation(): View
    {
        $records = Depreciation::query()
            ->with('asset')
            ->orderByDesc('period')
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('asset::depreciation', [
            'records' => $records,
            'defaultPeriod' => now()->format('Y-m'),
        ]);
    }

    /**
     * Jalankan penyusutan massal untuk satu periode (klik, idempoten).
     */
    public function runDepreciation(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'book' => 'required|in:commercial,fiscal',
        ]);

        $total = 0;
        $count = 0;

        foreach (Asset::query()->where('status', '!=', AssetStatus::Disposed)->cursor() as $asset) {
            try {
                $result = $this->depreciation->depreciate($asset, $data['period'], null, $data['book']);
            } catch (\Throwable) {
                continue;
            }

            if ($result['amount_idr'] > 0) {
                $count++;
                $total += $result['amount_idr'];
            }
        }

        return redirect()
            ->route('asset.depreciation.index')
            ->with(
                'success',
                'Penyusutan '.$data['book'].' periode '.$data['period'].': '.$count.' aset, total '.number_format($total).' IDR (idempoten — pemanggilan ulang tidak menggandakan).'
            );
    }
}
