<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Tampilkan form buat booking baru (untuk Customer)
     */
    public function create()
    {
        $services = Service::active()->get();
        return view('bookings.create', compact('services'));
    }

    /**
     * Simpan booking baru dari Customer
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'plate_number' => 'required|string|max:15',
            'vehicle_brand' => 'required|string|max:100',
            'vehicle_model' => 'nullable|string|max:100',
            'vehicle_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'complaint' => 'required|string|max:1000',
            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'nullable',
        ]);

        $service = Service::findOrFail($validated['service_id']);

        $booking = Booking::create([
            'booking_code' => Booking::generateBookingCode(),
            'customer_id' => auth()->id(),
            'service_id' => $validated['service_id'],
            'plate_number' => strtoupper($validated['plate_number']),
            'vehicle_brand' => $validated['vehicle_brand'],
            'vehicle_model' => $validated['vehicle_model'] ?? null,
            'vehicle_year' => $validated['vehicle_year'] ?? null,
            'complaint' => $validated['complaint'],
            'booking_date' => $validated['booking_date'],
            'booking_time' => $validated['booking_time'] ?? null,
            'status' => 'pending',
            'service_cost' => $service->price,
        ]);

        return redirect()->route('dashboard')
            ->with('success', "Booking {$booking->booking_code} berhasil dibuat!");
    }

    /**
     * Detail booking (untuk semua role)
     */
    public function show(Booking $booking)
    {
        // Customer hanya bisa lihat booking miliknya
        if (auth()->user()->isCustomer() && $booking->customer_id !== auth()->id()) {
            abort(403);
        }

        $booking->load(['customer', 'mechanic', 'service', 'spareparts']);
        $spareparts = Sparepart::active()->orderBy('name')->get();

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

        // Pastikan user yang dipilih memang mekanik
        $mechanic = User::where('id', $request->mechanic_id)
            ->where('role', 'mekanik')
            ->firstOrFail();

        $booking->update([
            'mechanic_id' => $mechanic->id,
            'status' => 'confirmed',
        ]);

        return back()->with('success', "Mekanik {$mechanic->name} berhasil di-assign.");
    }

    /**
     * Update status booking (Mekanik/Admin)
     * Flow: pending -> confirmed -> in_progress -> completed -> invoiced
     */
    public function updateStatus(Request $request, Booking $booking)
    {
        $request->validate([
            'status' => 'required|in:pending,confirmed,in_progress,completed,invoiced',
            'mechanic_notes' => 'nullable|string|max:2000',
        ]);

        $newStatus = $request->status;

        // ============================================================
        // CORE LOGIC: Saat status diubah ke "completed",
        // jalankan DB Transaction untuk potong stok sparepart
        // dan hitung grand total
        // ============================================================
        if ($newStatus === 'completed') {
            DB::transaction(function () use ($booking, $request) {
                // 1. Potong stok semua sparepart yang dipakai
                foreach ($booking->spareparts as $sparepart) {
                    $qty = $sparepart->pivot->quantity;

                    // Pastikan stok masih mencukupi (race condition safety)
                    $affected = DB::table('spareparts')
                        ->where('id', $sparepart->id)
                        ->where('stock', '>=', $qty)
                        ->decrement('stock', $qty);

                    if ($affected === 0) {
                        throw new \Exception(
                            "Stok {$sparepart->name} tidak mencukupi! Tersisa: {$sparepart->stock}, dibutuhkan: {$qty}"
                        );
                    }
                }

                // 2. Hitung ulang total biaya
                $booking->refresh();
                $sparepartCost = $booking->spareparts->sum('pivot.subtotal');

                $booking->update([
                    'status' => 'completed',
                    'mechanic_notes' => $request->mechanic_notes ?? $booking->mechanic_notes,
                    'sparepart_cost' => $sparepartCost,
                    'grand_total' => $booking->service_cost + $sparepartCost,
                ]);
            });

            return back()->with('success', 'Job selesai! Stok sparepart telah dipotong dan invoice siap dicetak.');
        }

        // Status selain completed: update biasa
        $booking->update([
            'status' => $newStatus,
            'mechanic_notes' => $request->mechanic_notes ?? $booking->mechanic_notes,
        ]);

        return back()->with('success', "Status diperbarui menjadi: {$newStatus}");
    }

    /**
     * Tambah sparepart ke booking (hanya saat status in_progress)
     * Dipanggil via form dinamis Alpine.js
     */
    public function addSparepart(Request $request, Booking $booking)
    {
        // Validasi: hanya bisa tambah sparepart saat in_progress
        if (!$booking->isInProgress()) {
            return back()->with('error', 'Sparepart hanya bisa ditambahkan saat status In Progress.');
        }

        $request->validate([
            'sparepart_id' => 'required|exists:spareparts,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $sparepart = Sparepart::findOrFail($request->sparepart_id);

        // Cek stok mencukupi
        if (!$sparepart->hasStock($request->quantity)) {
            return back()->with('error', "Stok {$sparepart->name} tidak mencukupi. Tersisa: {$sparepart->stock}");
        }

        $unitPrice = $sparepart->price;
        $subtotal = $unitPrice * $request->quantity;

        // Cek apakah sparepart sudah ada di booking (update qty jika ada)
        $existing = $booking->spareparts()->where('sparepart_id', $sparepart->id)->first();

        if ($existing) {
            $newQty = $existing->pivot->quantity + $request->quantity;

            if (!$sparepart->hasStock($newQty)) {
                return back()->with('error', "Total stok {$sparepart->name} tidak mencukupi.");
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

        // Recalculate costs (belum potong stok, stok dipotong saat completed)
        $booking->refresh();
        $sparepartCost = $booking->spareparts->sum('pivot.subtotal');
        $booking->update([
            'sparepart_cost' => $sparepartCost,
            'grand_total' => $booking->service_cost + $sparepartCost,
        ]);

        return back()->with('success', "Sparepart {$sparepart->name} (x{$request->quantity}) berhasil ditambahkan.");
    }

    /**
     * Hapus sparepart dari booking
     */
    public function removeSparepart(Booking $booking, Sparepart $sparepart)
    {
        if (!$booking->isInProgress()) {
            return back()->with('error', 'Sparepart hanya bisa dihapus saat status In Progress.');
        }

        $booking->spareparts()->detach($sparepart->id);

        // Recalculate
        $booking->refresh();
        $sparepartCost = $booking->spareparts->sum('pivot.subtotal');
        $booking->update([
            'sparepart_cost' => $sparepartCost,
            'grand_total' => $booking->service_cost + $sparepartCost,
        ]);

        return back()->with('success', 'Sparepart berhasil dihapus dari job order.');
    }

    /**
     * Cetak Invoice (view invoice)
     */
    public function invoice(Booking $booking)
    {
        if (!in_array($booking->status, ['completed', 'invoiced'])) {
            return back()->with('error', 'Invoice hanya tersedia untuk booking yang sudah selesai.');
        }

        $booking->load(['customer', 'mechanic', 'service', 'spareparts']);

        // Tandai sebagai invoiced
        if ($booking->status === 'completed') {
            $booking->update(['status' => 'invoiced']);
        }

        return view('bookings.invoice', compact('booking'));
    }
}
