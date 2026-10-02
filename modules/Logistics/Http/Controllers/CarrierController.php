<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\AssignCarrierToLegAction;
use Modules\Logistics\Application\Actions\CompleteShipmentLegAction;
use Modules\Logistics\Application\Actions\PayCarriersAction;
use Modules\Logistics\Domain\Models\Carrier;
use Modules\Logistics\Domain\Models\ShipmentLeg;
use Modules\Logistics\Domain\Services\ShipmentMarginReport;
use RuntimeException;

class CarrierController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()),
            403,
            'Akses ditolak. Halaman carrier dan margin khusus manajemen logistik.'
        );
    }

    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isLogisticsAdmin()), 403, 'Hanya admin logistik yang dapat mengelola carrier dan pembayarannya.');
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $carriers = Carrier::withCount('legs')
            ->withSum(['legs as accrued_unpaid_idr' => fn ($q) => $q->whereNotNull('cost_accrued_at')->whereNull('carrier_payment_id')], 'carrier_cost_idr')
            ->withSum('payments as paid_total_idr', 'amount_idr')
            ->orderBy('name')
            ->get();

        $legs = ShipmentLeg::with(['shipment', 'carrier', 'origin', 'destination'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('estimated_departure')
            ->limit(40)
            ->get();

        return view('logistics::carriers.index', [
            'carriers' => $carriers,
            'legs' => $legs,
            'canManage' => $request->user()->isAdmin() || $request->user()->isLogisticsAdmin(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'code' => 'required|string|max:24|alpha_dash|unique:lgx_carriers,code',
            'name' => 'required|string|max:120',
            'mode' => ['nullable', Rule::in(['road', 'sea', 'air'])],
            'payment_terms_days' => 'required|integer|min:0|max:120',
        ]);

        Carrier::create($data + ['is_active' => true]);

        return back()->with('success', "Carrier {$data['name']} ditambahkan.");
    }

    public function assignLeg(Request $request, int $leg, AssignCarrierToLegAction $action): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'carrier_id' => 'required|integer|exists:lgx_carriers,id',
            'cost_idr' => 'required|integer|min:1',
        ]);

        try {
            $action->execute(ShipmentLeg::findOrFail($leg), Carrier::findOrFail($data['carrier_id']), (int) $data['cost_idr']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Leg disubkontrakkan ke carrier.');
    }

    public function completeLeg(Request $request, int $leg, CompleteShipmentLegAction $action): RedirectResponse
    {
        $this->authorizeView($request);

        try {
            $done = $action->execute(ShipmentLeg::findOrFail($leg));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Leg diselesaikan'.($done->cost_accrued_at ? ' dan biaya carrier diakrual.' : '.'));
    }

    public function pay(Request $request, Carrier $carrier, PayCarriersAction $action): RedirectResponse
    {
        $this->authorizeManage($request);

        $payment = $action->execute($carrier, $request->user()->id);

        return $payment
            ? back()->with('success', "Pembayaran {$carrier->name}: Rp ".number_format($payment->amount_idr, 0, ',', '.')." ({$payment->leg_count} leg).")
            : back()->with('error', "Tidak ada utang {$carrier->name} yang jatuh tempo.");
    }

    public function margins(Request $request, ShipmentMarginReport $report): View
    {
        $this->authorizeView($request);

        $rows = $report->recent(50);

        return view('logistics::carriers.margins', [
            'rows' => $rows,
            'totalRevenue' => $rows->sum('revenue_idr'),
            'totalCost' => $rows->sum('carrier_cost_idr'),
            'totalMargin' => $rows->sum('margin_idr'),
        ]);
    }
}
