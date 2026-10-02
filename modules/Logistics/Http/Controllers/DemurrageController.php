<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\EndContainerDwellAction;
use Modules\Logistics\Application\Actions\GenerateDdInvoicesAction;
use Modules\Logistics\Application\Actions\StartContainerDwellAction;
use Modules\Logistics\Domain\Models\Container;
use Modules\Logistics\Domain\Models\ContainerDwell;
use Modules\Logistics\Domain\Models\DdTariff;
use Modules\Logistics\Domain\Models\Location;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use RuntimeException;

class DemurrageController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()), 403, 'Akses ditolak. Halaman D&D khusus manajemen logistik.');
    }

    protected function authorizeManage(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && ($user->isAdmin() || $user->isLogisticsAdmin()), 403, 'Hanya admin logistik yang dapat mengelola tarif dan penagihan D&D.');
    }

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        return view('logistics::demurrage.index', [
            'tariffs' => DdTariff::with('location')->orderBy('kind')->orderBy('id')->get(),
            'dwells' => ContainerDwell::with(['container', 'shipment', 'location', 'invoice'])->orderByRaw("case status when 'open' then 0 else 1 end")->latest('id')->limit(50)->get(),
            'locations' => Location::orderBy('name')->get(),
            'canManage' => $request->user()->isAdmin() || $request->user()->isLogisticsAdmin(),
        ]);
    }

    public function storeTariff(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'kind' => ['required', Rule::in(DdTariff::KINDS)],
            'location_id' => 'nullable|integer|exists:lgx_locations,id',
            'size_type' => 'nullable|string|max:8',
            'free_days' => 'required|integer|min:0|max:60',
            'rate_per_day_idr' => 'required|integer|min:1',
            'escalation_after_days' => 'nullable|integer|min:1',
            'escalated_rate_per_day_idr' => 'nullable|integer|min:1|required_with:escalation_after_days',
        ]);

        DdTariff::create($data + ['is_active' => true]);

        return back()->with('success', 'Tarif D&D disimpan.');
    }

    public function start(Request $request, StartContainerDwellAction $action): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'container_number' => 'required|string|max:11',
            'tracking_number' => 'required|string|max:32',
            'location_id' => 'required|integer|exists:lgx_locations,id',
            'kind' => ['required', Rule::in(DdTariff::KINDS)],
        ]);

        $container = Container::where('container_number', strtoupper(trim($data['container_number'])))->first();
        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($data['tracking_number']))->first();
        if (! $container || ! $shipment) {
            return back()->withInput()->with('error', 'Kontainer atau nomor resi tidak ditemukan.');
        }

        try {
            $action->execute($container, $shipment, Location::findOrFail($data['location_id']), $data['kind']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hitungan '.$data['kind'].' dimulai.');
    }

    public function end(Request $request, int $dwell, EndContainerDwellAction $action): RedirectResponse
    {
        $this->authorizeManage($request);

        try {
            $closed = $action->execute(ContainerDwell::findOrFail($dwell));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Hitungan ditutup. Total Rp '.number_format($closed->accrued_amount_idr, 0, ',', '.').'.');
    }

    public function invoice(Request $request, GenerateDdInvoicesAction $action): RedirectResponse
    {
        $this->authorizeManage($request);

        $invoices = $action->execute();

        return $invoices->isEmpty()
            ? back()->with('error', 'Tidak ada hitungan tertutup yang perlu ditagih.')
            : back()->with('success', $invoices->count().' invoice D&D diterbitkan.');
    }
}
