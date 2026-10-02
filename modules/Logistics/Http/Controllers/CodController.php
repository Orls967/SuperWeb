<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\DepositCodCashAction;
use Modules\Logistics\Domain\Models\CodCollection;
use Modules\Logistics\Domain\Models\Driver;
use Modules\Logistics\Domain\Models\Location;
use RuntimeException;

class CodController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher() || $user->isHubOperator()),
            403,
            'Akses ditolak. Dashboard COD khusus staf operasional logistik.'
        );
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $user = $request->user();

        $totals = CodCollection::query()
            ->selectRaw('status, count(*) as total, sum(amount_idr) as amount, sum(fee_idr) as fee, sum(net_amount_idr) as net')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $sum = fn (string $status, string $col = 'amount') => (int) ($totals[$status]->{$col} ?? 0);
        $days = (int) config('logistics.cod_settlement_days', 2);

        $driverOutstanding = CodCollection::where('status', CodCollection::STATUS_COLLECTED)
            ->with('driver.user')
            ->selectRaw('driver_id, count(*) as shipments, sum(amount_idr) as amount, min(collected_at) as oldest_at')
            ->groupBy('driver_id')
            ->orderByDesc('amount')
            ->get();

        $driverIds = $driverOutstanding->pluck('driver_id');
        $drivers = Driver::with('user')->whereIn('id', $driverIds)->get()->keyBy('id');

        return view('logistics::cod.index', [
            'collectedAmount' => $sum(CodCollection::STATUS_COLLECTED),
            'depositedAmount' => $sum(CodCollection::STATUS_DEPOSITED),
            'settledAmount' => $sum(CodCollection::STATUS_SETTLED),
            'settledFee' => $sum(CodCollection::STATUS_SETTLED, 'fee'),
            'settledNet' => $sum(CodCollection::STATUS_SETTLED, 'net'),
            'counts' => [
                'collected' => $sum(CodCollection::STATUS_COLLECTED, 'total'),
                'deposited' => $sum(CodCollection::STATUS_DEPOSITED, 'total'),
                'settled' => $sum(CodCollection::STATUS_SETTLED, 'total'),
            ],
            'driverOutstanding' => $driverOutstanding,
            'drivers' => $drivers,
            'pendingSettlement' => CodCollection::where('status', CodCollection::STATUS_DEPOSITED)->with(['shipment', 'depositHub'])->orderBy('deposited_at')->limit(50)->get(),
            'recent' => CodCollection::with(['shipment', 'driver.user'])->latest('id')->limit(20)->get(),
            'settlementDays' => $days,
            'hubs' => $user->isHubOperator() ? Location::where('id', $user->assignedHubId())->get() : Location::orderBy('name')->get(),
            'canDeposit' => $user->isAdmin() || $user->isLogisticsAdmin() || $user->isHubOperator(),
        ]);
    }

    public function deposit(Request $request, DepositCodCashAction $action): RedirectResponse
    {
        $this->authorizeView($request);
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isLogisticsAdmin() || $user->isHubOperator(), 403, 'Hanya operator hub dan admin yang dapat menerima setoran COD.');

        $data = $request->validate([
            'driver_id' => 'required|integer|exists:lgx_drivers,id',
            'hub_id' => 'required|integer|exists:lgx_locations,id',
            'amount_idr' => 'required|integer|min:1',
        ]);

        try {
            $result = $action->execute($user, Location::findOrFail($data['hub_id']), Driver::findOrFail($data['driver_id']), (int) $data['amount_idr']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Setoran COD diterima: {$result['collections']} resi, Rp ".number_format($result['amount'], 0, ',', '.').'.');
    }
}
