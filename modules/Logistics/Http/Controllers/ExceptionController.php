<?php

declare(strict_types=1);

namespace Modules\Logistics\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Logistics\Application\Actions\RaiseShipmentExceptionAction;
use Modules\Logistics\Application\Actions\ResolveShipmentExceptionAction;
use Modules\Logistics\Domain\Enums\ExceptionSeverity;
use Modules\Logistics\Domain\Enums\ExceptionType;
use Modules\Logistics\Domain\Enums\ShipmentStatus;
use Modules\Logistics\Domain\Models\Shipment;
use Modules\Logistics\Domain\Models\ShipmentException;
use Modules\Logistics\Domain\Services\DeliverySlaPolicy;
use Modules\Logistics\Domain\ValueObjects\TrackingNumber;
use RuntimeException;

class ExceptionController extends Controller
{
    protected function authorizeView(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher() || $user->isHubOperator()),
            403,
            'Akses ditolak. Halaman exception dan SLA khusus staf operasional logistik.'
        );
    }

    protected function authorizeResolve(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user && ($user->isAdmin() || $user->isLogisticsAdmin() || $user->isDispatcher()),
            403,
            'Hanya dispatcher dan admin logistik yang dapat menyelesaikan exception.'
        );
    }

    public function index(Request $request, DeliverySlaPolicy $sla): View
    {
        $this->authorizeView($request);

        $exceptions = ShipmentException::with(['shipment', 'location', 'reporter'])
            ->where('status', ShipmentException::STATUS_OPEN)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->query('severity')))
            ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderBy('detected_at')
            ->limit(100)
            ->get();

        $breached = $sla->breachedQuery()->with(['origin', 'destination'])->orderBy('booked_at')->limit(50)->get();
        $atRisk = $sla->atRiskQuery()->with(['origin', 'destination'])->orderBy('booked_at')->limit(50)->get();

        return view('logistics::exceptions.index', [
            'exceptions' => $exceptions,
            'breached' => $breached,
            'atRisk' => $atRisk,
            'sla' => $sla,
            'types' => ExceptionType::cases(),
            'manualTypes' => ExceptionType::manual(),
            'severities' => ExceptionSeverity::cases(),
            'resumeStatuses' => [ShipmentStatus::InTransit, ShipmentStatus::AtHub, ShipmentStatus::OutForDelivery, ShipmentStatus::ReturnToSender],
            'canResolve' => $request->user()->isAdmin() || $request->user()->isLogisticsAdmin() || $request->user()->isDispatcher(),
            'filters' => $request->only(['type', 'severity']),
        ]);
    }

    public function store(Request $request, RaiseShipmentExceptionAction $action): RedirectResponse
    {
        $this->authorizeView($request);

        $data = $request->validate([
            'tracking_number' => 'required|string|max:32',
            'type' => ['required', Rule::in(array_map(fn ($t) => $t->value, ExceptionType::manual()))],
            'description' => 'required|string|max:500',
        ]);

        $shipment = Shipment::where('tracking_number', TrackingNumber::normalize($data['tracking_number']))->first();
        if (! $shipment) {
            return back()->withInput()->with('error', 'Nomor resi tidak terdaftar di sistem.');
        }

        if (! in_array($shipment->status, DeliverySlaPolicy::ACTIVE_STATUSES, true)) {
            return back()->withInput()->with('error', "Resi {$shipment->tracking_number} berstatus '{$shipment->status->label()}' dan tidak dapat diberi exception baru.");
        }

        $type = ExceptionType::from($data['type']);
        $action->execute(
            shipment: $shipment,
            type: $type,
            description: $data['description'],
            reporter: $request->user(),
            locationId: $request->user()->assignedHubId(),
            markShipment: true,
        );

        return back()->with('success', "Exception '{$type->label()}' dicatat untuk resi {$shipment->tracking_number}.");
    }

    public function resolve(Request $request, int $exception, ResolveShipmentExceptionAction $action): RedirectResponse
    {
        $this->authorizeResolve($request);

        $data = $request->validate([
            'notes' => 'required|string|max:500',
            'resume_to' => ['nullable', Rule::enum(ShipmentStatus::class)],
        ]);

        try {
            $action->execute(
                ShipmentException::findOrFail($exception),
                $request->user(),
                $data['notes'],
                isset($data['resume_to']) ? ShipmentStatus::from($data['resume_to']) : null
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Exception diselesaikan.');
    }
}
