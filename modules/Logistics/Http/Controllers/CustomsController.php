<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\ClearCustomsAction;
use Modules\Logistics\Application\Actions\PayCustomsDutyAction;
use Modules\Logistics\Application\Actions\SubmitCustomsDeclarationAction;
use Modules\Logistics\Domain\Models\CustomsDeclaration;
use Modules\Logistics\Domain\Models\HsTariff;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use RuntimeException;

class CustomsController extends Controller
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

        return view('logistics::customs.index', [
            'declarations' => CustomsDeclaration::with('shipment')
                ->when(! $this->isStaff($request), fn ($q) => $q->whereHas('shipment', fn ($s) => $s->where('shipper_id', $user->id)))
                ->latest('id')->limit(50)->get(),
            'tariffs' => HsTariff::orderBy('hs_code')->limit(100)->get(),
            'isStaff' => $this->isStaff($request),
            'isOfficer' => $user->isAdmin() || $user->isLogisticsAdmin(),
            'userId' => $user->id,
        ]);
    }

    public function storeTariff(Request $request): RedirectResponse
    {
        abort_unless($request->user() && ($request->user()->isAdmin() || $request->user()->isLogisticsAdmin()), 403, 'Hanya admin logistik yang dapat mengelola tarif HS.');

        $data = $request->validate([
            'hs_code' => 'required|digits:8|unique:lgx_hs_tariffs,hs_code',
            'description' => 'required|string|max:255',
            'bm_bp' => 'required|integer|min:0|max:10000',
            'ppn_bp' => 'required|integer|min:0|max:10000',
            'pph22_api_bp' => 'required|integer|min:0|max:10000',
            'pph22_non_api_bp' => 'required|integer|min:0|max:10000',
            'requires_inspection' => 'nullable|boolean',
        ]);

        HsTariff::create($data + ['requires_inspection' => $request->boolean('requires_inspection'), 'is_active' => true]);

        return back()->with('success', 'Tarif HS disimpan.');
    }

    public function store(Request $request, SubmitCustomsDeclarationAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'tracking_number' => 'required|string|max:32',
            'type' => ['required', Rule::in(['PIB', 'PEB'])],
            'has_api' => 'nullable|boolean',
            'lines' => 'required|array|min:1|max:20',
            'lines.*.hs_code' => 'required|string|max:12',
            'lines.*.value_idr' => 'required|integer|min:1',
        ]);

        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($data['tracking_number']))->first();
        if (! $shipment || (! $this->isStaff($request) && $shipment->shipper_id !== $request->user()->id)) {
            return back()->withInput()->with('error', 'Nomor resi tidak ditemukan.');
        }

        try {
            $declaration = $action->execute($request->user(), $shipment, $data['type'], $data['lines'], $request->boolean('has_api'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with($declaration->lane === 'red' ? 'warning' : 'success', "Dokumen {$declaration->declaration_number} diajukan (jalur {$declaration->lane}). Total simulasi bea & pajak Rp ".number_format($declaration->total_duty_idr, 0, ',', '.').'.');
    }

    public function pay(Request $request, int $declaration, PayCustomsDutyAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate(['pin' => 'required|string']);

        try {
            $action->execute($request->user(), CustomsDeclaration::findOrFail($declaration), $data['pin']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran bea cukai berhasil.');
    }

    public function clear(Request $request, int $declaration, ClearCustomsAction $action): RedirectResponse
    {
        $this->authorizeAccess($request);

        try {
            $action->execute($request->user(), CustomsDeclaration::findOrFail($declaration));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Dokumen diloloskan (SPPB simulasi).');
    }
}
