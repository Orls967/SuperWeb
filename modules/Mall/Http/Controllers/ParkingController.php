<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Mall\Application\Actions\RegisterParkingMemberAction;
use Modules\Mall\Application\Actions\ValidateParkingAction;
use Modules\Mall\Application\Queries\FootfallAnalyticsQuery;
use Modules\Mall\Application\Queries\ParkingDashboardQuery;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Models\ParkingMember;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\Property;

class ParkingController extends Controller
{
    /**
     * Dashboard operasional parkir: okupansi zona, sesi aktif, pendapatan hari ini.
     */
    public function index(Request $request, ParkingDashboardQuery $query): View
    {
        $property = $this->resolveProperty($request);
        abort_if($property === null, 404, 'Properti mall belum tersedia.');

        $sessions = ParkingSession::with(['zone', 'member', 'validatedByTenant'])
            ->where('property_id', $property->id)
            ->when(
                $request->string('status')->toString() === 'active',
                fn ($q) => $q->where('status', ParkingSessionStatus::ACTIVE)
            )
            ->latest('entry_time')
            ->cursorPaginate(20)
            ->withQueryString();

        return view('mall::parking.index', [
            'property' => $property,
            'properties' => Property::where('is_active', true)->get(),
            'stats' => $query->execute($property->id),
            'sessions' => $sessions,
            'statusFilter' => $request->string('status')->toString(),
        ]);
    }

    /**
     * Daftar & pendaftaran langganan parkir bulanan.
     */
    public function members(Request $request): View
    {
        $property = $this->resolveProperty($request);
        abort_if($property === null, 404, 'Properti mall belum tersedia.');

        $user = $request->user();
        $isStaff = $user !== null && ($user->isAdmin() || $user->isMallAdmin());

        $members = ParkingMember::with(['user', 'sessions'])
            ->where('property_id', $property->id)
            // Customer hanya melihat langganannya sendiri
            ->when(! $isStaff, fn ($q) => $q->where('user_id', $user?->id))
            ->orderByDesc('end_date')
            ->cursorPaginate(20)
            ->withQueryString();

        return view('mall::parking.members', [
            'property' => $property,
            'members' => $members,
            'isStaff' => $isStaff,
            'monthlyPrice' => RegisterParkingMemberAction::DEFAULT_MONTHLY_PRICE,
            'garageVehicles' => $user !== null
                ? Vehicle::where('user_id', $user->id)->whereNotNull('plate_number')->get()
                : collect(),
        ]);
    }

    public function storeMember(Request $request, RegisterParkingMemberAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:mall_properties,id'],
            'vehicle_id' => ['required', 'integer', 'exists:core_vehicles,id'],
            'months' => ['required', 'integer', 'min:1', 'max:12'],
            'pin' => ['required', 'string', 'digits:6'],
            'auto_renew' => ['nullable', 'boolean'],
        ], [
            'pin.digits' => 'PIN dompet harus 6 digit angka.',
            'vehicle_id.required' => 'Pilih kendaraan dari My Garage terlebih dahulu.',
        ]);

        try {
            $member = $action->execute(
                property: Property::findOrFail($validated['property_id']),
                user: $request->user(),
                vehicle: Vehicle::findOrFail($validated['vehicle_id']),
                pin: $validated['pin'],
                months: (int) $validated['months'],
                autoRenew: $request->boolean('auto_renew'),
            );
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('mall.parking.members')
            ->with('success', "Langganan parkir {$member->member_number} aktif sampai {$member->end_date->format('d/m/Y')}.");
    }

    /**
     * Validasi parkir manual dari portal tenant (POS Resto memakai contract langsung).
     */
    public function validateTicket(Request $request, ValidateParkingAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_number' => ['required', 'string', 'max:50'],
            'tenant_ref' => ['required', 'string', 'max:50'],
            'spend_amount' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $result = $action->validateTicket(
                ticketNumber: $validated['ticket_number'],
                tenantExternalRef: $validated['tenant_ref'],
                spendAmount: (int) $validated['spend_amount'],
            );
        } catch (InvalidParkingTicketException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result->receiptLine());
    }

    /**
     * Analitik kunjungan (footfall) dan konversi terhadap transaksi tenant.
     */
    public function footfall(Request $request, FootfallAnalyticsQuery $query): View
    {
        $property = $this->resolveProperty($request);
        abort_if($property === null, 404, 'Properti mall belum tersedia.');

        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return view('mall::parking.footfall', [
            'property' => $property,
            'properties' => Property::where('is_active', true)->get(),
            'days' => $days,
            'analytics' => $query->execute($property->id, $days),
        ]);
    }

    private function resolveProperty(Request $request): ?Property
    {
        if ($request->filled('property_id')) {
            return Property::find($request->integer('property_id'));
        }

        return Property::where('is_active', true)->orderBy('id')->first();
    }
}
