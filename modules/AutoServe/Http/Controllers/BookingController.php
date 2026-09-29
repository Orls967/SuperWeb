<?php

declare(strict_types=1);

namespace Modules\AutoServe\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Modules\AutoServe\Domain\Models\Booking;
use Modules\AutoServe\Domain\Models\Service;
use Modules\AutoServe\Domain\Models\Sparepart;

class BookingController extends Controller
{
    /**
     * Tampilkan form booking servis baru (Customer)
     */
    public function create()
    {
        $services = Service::active()->get();
        $vehicles = auth()->user()
            ? auth()->user()->activeVehicles()->with('car.brand')->get()
            : collect();

        return view('bookings.create', compact('services', 'vehicles'));
    }

    /**
     * Simpan booking servis baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:serve_services,id',
            'plate_number' => 'required|string|max:15',
            'vehicle_brand' => 'required|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'complaint' => 'required|string|max:1000',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'nullable',
            'vehicle_id' => 'nullable|exists:core_vehicles,id',
            'save_to_garage' => 'nullable|boolean',
        ]);

        $service = Service::findOrFail($validated['service_id']);

        $data = [
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => auth()->id(),
            'service_id' => $service->id,
            'plate_number' => strtoupper($validated['plate_number']),
            'vehicle_brand' => $validated['vehicle_brand'],
            'vehicle_model' => $validated['vehicle_model'] ?? null,
            'vehicle_year' => $validated['vehicle_year'] ?? null,
            'complaint' => $validated['complaint'],
            'booking_date' => $validated['booking_date'],
            'booking_time' => $validated['booking_time'] ?? null,
            'status' => 'pending',
            'service_cost' => $service->price,
            'sparepart_cost' => 0,
            'grand_total' => $service->price,
        ];

        if (! empty($validated['vehicle_id'])) {
            $data['vehicle_id'] = $validated['vehicle_id'];
        } elseif ($request->boolean('save_to_garage')) {
            $createdVehicle = \Modules\Core\Domain\Models\Vehicle::create([
                'user_id' => auth()->id(),
                'plate_number' => strtoupper(trim($validated['plate_number'])),
                'color' => $request->input('color'),
                'status' => 'active',
                'acquired_at' => now(),
                'acquired_via_type' => 'manual',
            ]);
            $data['vehicle_id'] = $createdVehicle->id;
        }

        $booking = Booking::create($data);

        return redirect()->route('dashboard')
            ->with('success', "Booking {$booking->booking_code} berhasil dibuat!");
    }

    /**
     * Tampilkan detail Job Order / Booking
     */
    public function show(Booking $booking)
    {
        $user = auth()->user();

        if ($user->isCustomer() && $booking->customer_id !== $user->id) {
            abort(403, 'Anda tidak memiliki akses ke booking ini.');
        }

        $booking->load(['customer', 'mechanic', 'service', 'spareparts']);
        $spareparts = Sparepart::active()->where('stock', '>', 0)->get();

        return view('bookings.show', compact('booking', 'spareparts'));
    }

    /**
     * Assign mekanik ke booking (Admin only)
     */
    public function assignMechanic(Request $request, Booking $booking)
    {
        $request->validate([
            'mechanic_id' => 'required|exists:users,id',
        ]);

        $mechanic = User::findOrFail($request->mechanic_id);

        if (! $mechanic->isMekanik()) {
            return back()->with('error', 'User yang dipilih bukan seorang mekanik.');
        }

        $booking->update([
            'mechanic_id' => $mechanic->id,
            'status' => 'confirmed',
        ]);

        return back()->with('success', "Mekanik {$mechanic->name} berhasil di-assign.");
    }

    /**
     * Update status booking (Mekanik/Admin)
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,in_progress,waiting_parts,completed,invoiced,cancelled',
            'mechanic_notes' => 'nullable|string|max:2000',
        ]);

        $newStatus = $request->status;

        if ($newStatus === 'completed') {
            app(\Modules\AutoServe\Application\Actions\CompleteBookingAction::class)->handle(
                $booking,
                $request->mechanic_notes
            );

            return back()->with('success', 'Servis selesai! Stok sparepart telah dipotong dan total biaya telah dihitung.');
        }

        $booking->update([
            'status' => $newStatus,
            'mechanic_notes' => $request->mechanic_notes ?? $booking->mechanic_notes,
        ]);

        return back()->with('success', "Status diperbarui menjadi: {$newStatus}");
    }

    /**
     * Tambah sparepart ke booking
     */
    public function addSparepart(Request $request, Booking $booking)
    {
        if (! $booking->isInProgress()) {
            return back()->with('error', 'Sparepart hanya bisa ditambahkan saat status In Progress.');
        }

        $request->validate([
            'sparepart_id' => 'required|exists:serve_spareparts,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $sparepart = Sparepart::findOrFail($request->sparepart_id);

        if (! $sparepart->hasStock($request->quantity)) {
            return back()->with('error', "Stok {$sparepart->name} tidak mencukupi. Tersisa: {$sparepart->stock}");
        }

        $unitPrice = $sparepart->price;
        $subtotal = $unitPrice * $request->quantity;

        $existing = $booking->spareparts()->where('sparepart_id', $sparepart->id)->first();

        if ($existing) {
            $newQty = $existing->pivot->quantity + $request->quantity;

            if (! $sparepart->hasStock($newQty)) {
                return back()->with('error', "Total quantity ({$newQty}) melebihi stok yang ada ({$sparepart->stock}).");
            }

            $booking->spareparts()->updateExistingPivot($sparepart->id, [
                'quantity' => $newQty,
                'subtotal' => $unitPrice * $newQty,
            ]);
        } else {
            $booking->spareparts()->attach($sparepart->id, [
                'quantity' => $request->quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ]);
        }

        $booking->refresh();
        $booking->recalculateCosts();

        return back()->with('success', "Sparepart {$sparepart->name} ({$request->quantity} {$sparepart->unit}) berhasil ditambahkan.");
    }

    /**
     * Hapus sparepart dari booking
     */
    public function removeSparepart(Booking $booking, Sparepart $sparepart)
    {
        if (! $booking->isInProgress()) {
            return back()->with('error', 'Sparepart hanya bisa diubah saat status In Progress.');
        }

        $booking->spareparts()->detach($sparepart->id);
        $booking->refresh();
        $booking->recalculateCosts();

        return back()->with('success', "Sparepart {$sparepart->name} berhasil dihapus dari job order.");
    }

    /**
     * Cetak Invoice
     */
    public function invoice(Booking $booking)
    {
        $user = auth()->user();

        if ($user->isCustomer() && $booking->customer_id !== $user->id) {
            abort(403);
        }

        if (! in_array($booking->status, ['completed', 'invoiced'])) {
            return back()->with('error', 'Invoice hanya tersedia untuk booking yang sudah selesai.');
        }

        $booking->load(['customer', 'mechanic', 'service', 'spareparts']);

        if ($booking->status === 'completed') {
            $booking->update(['status' => 'invoiced']);
        }

        return view('bookings.invoice', compact('booking'));
    }
}
