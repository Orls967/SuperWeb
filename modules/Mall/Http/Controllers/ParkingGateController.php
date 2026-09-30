<?php

declare(strict_types=1);

namespace Modules\Mall\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Mall\Application\Actions\CheckInVehicleAction;
use Modules\Mall\Application\Actions\CheckOutVehicleAction;
use Modules\Mall\Application\Actions\ReportLostTicketAction;
use Modules\Mall\Application\Actions\SettleParkingSessionAction;
use Modules\Mall\Domain\Enums\ParkingPaymentMethod;
use Modules\Mall\Domain\Enums\ParkingSessionStatus;
use Modules\Mall\Domain\Enums\VehicleType;
use Modules\Mall\Domain\Exceptions\InvalidParkingTicketException;
use Modules\Mall\Domain\Exceptions\ParkingZoneFullException;
use Modules\Mall\Domain\Exceptions\TicketAlreadySettledException;
use Modules\Mall\Domain\Models\ParkingSession;
use Modules\Mall\Domain\Models\ParkingZone;
use Modules\Mall\Domain\Models\Property;
use Modules\Mall\Http\Requests\CheckInVehicleRequest;
use Modules\Mall\Http\Requests\SettleParkingRequest;

class ParkingGateController extends Controller
{
    /**
     * Layar operator gate masuk.
     */
    public function entry(Request $request): View
    {
        $property = $this->resolveProperty($request);

        return view('mall::parking.gate-entry', [
            'property' => $property,
            'properties' => Property::where('is_active', true)->get(),
            'zones' => ParkingZone::where('property_id', $property?->id)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
            'vehicleTypes' => VehicleType::cases(),
            'recentSessions' => ParkingSession::with('zone')
                ->where('property_id', $property?->id)
                ->where('status', ParkingSessionStatus::ACTIVE)
                ->latest('entry_time')
                ->limit(10)
                ->get(),
        ]);
    }

    public function checkIn(CheckInVehicleRequest $request, CheckInVehicleAction $action): RedirectResponse
    {
        $property = Property::findOrFail($request->integer('property_id'));

        try {
            $session = $action->execute(
                property: $property,
                plateNumber: $request->string('plate_number')->toString(),
                vehicleType: VehicleType::from($request->string('vehicle_type')->toString()),
                entryGate: $request->string('entry_gate')->toString() ?: 'Gate Masuk 1',
                parkingZoneId: $request->filled('parking_zone_id') ? $request->integer('parking_zone_id') : null,
            );
        } catch (ParkingZoneFullException $e) {
            $alternatives = ParkingZone::where('property_id', $property->id)
                ->where('is_active', true)
                ->get()
                ->filter(fn (ParkingZone $zone) => ! $zone->isFull())
                ->pluck('name')
                ->implode(', ');

            return back()
                ->withInput()
                ->with('error', $e->getMessage().($alternatives !== '' ? " Zona yang masih tersedia: {$alternatives}." : ''));
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Tiket {$session->ticket_number} diterbitkan untuk {$session->plate_number}. Palang masuk dibuka.");
    }

    /**
     * Layar operator gate keluar.
     */
    public function exit(Request $request, CheckOutVehicleAction $checkOut): View
    {
        $property = $this->resolveProperty($request);
        $ticket = $request->string('ticket')->toString();
        $session = null;
        $quote = null;
        $error = null;

        if ($ticket !== '') {
            $session = ParkingSession::with(['zone', 'member', 'validatedByTenant'])
                ->where('ticket_number', $ticket)
                ->first();

            if ($session === null) {
                $error = "Tiket {$ticket} tidak ditemukan.";
            } elseif ($session->status === ParkingSessionStatus::COMPLETED) {
                $error = "Tiket {$ticket} sudah selesai pada {$session->exit_time?->format('d/m/Y H:i')}.";
            } else {
                $quote = $checkOut->quote($session);
            }
        }

        return view('mall::parking.gate-exit', [
            'property' => $property,
            'ticket' => $ticket,
            'session' => $session,
            'quote' => $quote,
            'lookupError' => $error,
        ]);
    }

    /**
     * Finalisasi tarif; tiket gratis langsung selesai, sisanya menunggu pembayaran.
     */
    public function checkOut(Request $request, CheckOutVehicleAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'ticket_number' => ['required', 'string', 'exists:mall_parking_sessions,ticket_number'],
            'exit_gate' => ['nullable', 'string', 'max:50'],
        ]);

        $session = ParkingSession::where('ticket_number', $validated['ticket_number'])->firstOrFail();

        try {
            $session = $action->execute($session, $validated['exit_gate'] ?? 'Gate Keluar 1');
        } catch (TicketAlreadySettledException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($session->isCompleted()) {
            return redirect()
                ->route('mall.parking.gate.exit', ['property_id' => $session->property_id])
                ->with('success', "Tiket {$session->ticket_number} bebas biaya. Palang keluar dibuka.");
        }

        return redirect()
            ->route('mall.parking.gate.exit', ['ticket' => $session->ticket_number])
            ->with('success', 'Tarif dihitung: Rp '.number_format($session->total_fee, 0, ',', '.').'. Silakan terima pembayaran.');
    }

    public function settle(SettleParkingRequest $request, SettleParkingSessionAction $action, string $ticketNumber): RedirectResponse
    {
        $session = ParkingSession::where('ticket_number', $ticketNumber)->firstOrFail();
        $method = ParkingPaymentMethod::from($request->string('payment_method')->toString());

        $payer = $request->filled('payer_email')
            ? User::where('email', $request->string('payer_email')->toString())->first()
            : null;

        try {
            $session = $action->execute(
                session: $session,
                method: $method,
                payer: $payer,
                pin: $request->filled('pin') ? $request->string('pin')->toString() : null,
                cashTendered: $request->filled('cash_tendered') ? $request->integer('cash_tendered') : null,
                idempotencyKey: $request->filled('idempotency_key')
                    ? 'mall:parking:settle:'.$request->string('idempotency_key')->toString()
                    : null,
            );
        } catch (TicketAlreadySettledException|InvalidParkingTicketException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $change = $request->filled('cash_tendered')
            ? max(0, $request->integer('cash_tendered') - $session->total_fee)
            : 0;

        return redirect()
            ->route('mall.parking.gate.exit', ['property_id' => $session->property_id])
            ->with('success', "Tiket {$session->ticket_number} lunas ({$method->label()})."
                .($change > 0 ? ' Kembalian Rp '.number_format($change, 0, ',', '.').'.' : '')
                .' Palang keluar dibuka.');
    }

    public function lostTicket(Request $request, ReportLostTicketAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'property_id' => ['required', 'integer', 'exists:mall_properties,id'],
            'plate_number' => ['required', 'string', 'max:20'],
        ]);

        try {
            $session = $action->execute(
                propertyId: $validated['property_id'],
                plateNumber: $validated['plate_number'],
            );
        } catch (InvalidParkingTicketException|TicketAlreadySettledException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('mall.parking.gate.exit', ['ticket' => $session->ticket_number])
            ->with('success', "Tiket hilang dicatat untuk {$session->plate_number}. Tarif termasuk denda: Rp "
                .number_format($session->total_fee, 0, ',', '.').'.');
    }

    /**
     * Occupancy real-time untuk polling Alpine di layar gate.
     */
    public function occupancy(Request $request): JsonResponse
    {
        $property = $this->resolveProperty($request);

        $zones = ParkingZone::where('property_id', $property?->id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (ParkingZone $zone) => [
                'id' => $zone->id,
                'code' => $zone->code,
                'name' => $zone->name,
                'vehicle_type' => $zone->vehicle_type->label(),
                'capacity' => $zone->total_capacity,
                'occupied' => $zone->current_occupancy,
                'available' => $zone->availableSlots(),
                'rate' => $zone->occupancyRate(),
                'is_full' => $zone->isFull(),
            ]);

        return response()->json([
            'property' => $property?->name,
            'updated_at' => now()->format('H:i:s'),
            'zones' => $zones,
            'total_capacity' => (int) $zones->sum('capacity'),
            'total_occupied' => (int) $zones->sum('occupied'),
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
