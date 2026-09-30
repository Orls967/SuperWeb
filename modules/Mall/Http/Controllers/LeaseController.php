<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\ActivateLeaseAction;
use Modules\Mall\Application\Actions\CreateLeaseAction;
use Modules\Mall\Application\Actions\RenewLeaseAction;
use Modules\Mall\Application\Actions\TerminateLeaseAction;
use Modules\Mall\Domain\Enums\LeaseStatus;
use Modules\Mall\Domain\Enums\RentModel;
use Modules\Mall\Domain\Models\Lease;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Domain\Models\Tenant;
use Modules\Mall\Domain\Models\Unit;

class LeaseController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status');
        $propertyId = $request->input('property_id');
        $expiringOnly = $request->boolean('expiring_soon');

        $query = Lease::with(['property', 'unit', 'tenant'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId));

        if ($expiringOnly) {
            $query->where('status', LeaseStatus::ACTIVE)
                ->whereDate('end_date', '<=', now()->addDays(90))
                ->whereDate('end_date', '>=', now());
        }

        $leases = $query->latest('id')->paginate(15);
        $properties = Property::where('is_active', true)->get();

        return view('mall::leases.index', [
            'leases' => $leases,
            'properties' => $properties,
            'selectedStatus' => $status,
            'selectedPropertyId' => $propertyId ? (int) $propertyId : null,
            'expiringOnly' => $expiringOnly,
        ]);
    }

    public function create(): View
    {
        $properties = Property::where('is_active', true)->with('units')->get();
        $tenants = Tenant::where('is_active', true)->get();
        $units = Unit::with('property')->get();

        return view('mall::leases.create', [
            'properties' => $properties,
            'tenants' => $tenants,
            'units' => $units,
            'rentModels' => RentModel::cases(),
        ]);
    }

    public function store(Request $request, CreateLeaseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'exists:mall_properties,id'],
            'unit_id' => ['required', 'exists:mall_units,id'],
            'tenant_id' => ['required', 'exists:mall_tenants,id'],
            'rent_model' => ['required', 'string', 'in:fixed,revenue_share,greater_of'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'base_monthly_rent' => ['nullable', 'integer', 'min:0'],
            'service_charge_monthly' => ['nullable', 'integer', 'min:0'],
            'fit_out_days' => ['required', 'integer', 'min:0', 'max:180'],
            'annual_escalation_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'revenue_share_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'security_deposit_months' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $lease = $action->handle(
            propertyId: (int) $validated['property_id'],
            unitId: (int) $validated['unit_id'],
            tenantId: (int) $validated['tenant_id'],
            rentModel: $validated['rent_model'],
            startDate: $validated['start_date'],
            endDate: $validated['end_date'],
            baseMonthlyRent: isset($validated['base_monthly_rent']) ? (int) $validated['base_monthly_rent'] : null,
            serviceChargeMonthly: isset($validated['service_charge_monthly']) ? (int) $validated['service_charge_monthly'] : null,
            fitOutDays: (int) $validated['fit_out_days'],
            annualEscalationPercent: (float) $validated['annual_escalation_percent'],
            revenueSharePercent: isset($validated['revenue_share_percent']) ? (float) $validated['revenue_share_percent'] : null,
            securityDepositMonths: (int) $validated['security_deposit_months']
        );

        return redirect()->route('mall.leases.show', $lease)
            ->with('success', "Draft kontrak sewa {$lease->lease_number} berhasil dibuat. Silakan aktivasi dengan pembayaran deposit.");
    }

    public function show(Lease $lease): View
    {
        $lease->load(['property', 'unit.zone', 'tenant.user']);

        return view('mall::leases.show', [
            'lease' => $lease,
        ]);
    }

    public function activate(Request $request, Lease $lease, ActivateLeaseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'pin' => ['nullable', 'string', 'size:6'],
        ]);

        $action->handle(
            lease: $lease,
            payerUser: $request->user(),
            pin: $validated['pin'] ?? null
        );

        return back()->with('success', "Kontrak sewa {$lease->lease_number} berhasil diaktifkan dan deposit jaminan telah dibukukan.");
    }

    public function terminate(Request $request, Lease $lease, TerminateLeaseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'outstanding_deductions' => ['nullable', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $action->handle(
            lease: $lease,
            outstandingDeductions: (int) ($validated['outstanding_deductions'] ?? 0),
            reason: $validated['reason']
        );

        return back()->with('success', "Kontrak sewa {$lease->lease_number} berhasil diterminasi dan deposit telah diselesaikan.");
    }

    public function renew(Request $request, Lease $lease, RenewLeaseAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'new_end_date' => ['required', 'date', 'after:'.$lease->end_date->toDateString()],
            'annual_escalation_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],
        ]);

        $newLease = $action->handle(
            oldLease: $lease,
            newEndDate: $validated['new_end_date'],
            annualEscalationPercent: isset($validated['annual_escalation_percent']) ? (float) $validated['annual_escalation_percent'] : null
        );

        return redirect()->route('mall.leases.show', $newLease)
            ->with('success', "Kontrak sewa berhasil diperpanjang. Draft kontrak baru: {$newLease->lease_number}");
    }
}
