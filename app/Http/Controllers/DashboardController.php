<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Data statistik untuk dashboard
        $stats = [];

        if ($user->isStaff()) {
            // Admin/Mekanik: lihat semua data
            $stats = [
                'total_bookings' => Booking::count(),
                'pending_bookings' => Booking::where('status', 'pending')->count(),
                'in_progress' => Booking::where('status', 'in_progress')->count(),
                'completed_today' => Booking::where('status', 'completed')
                    ->whereDate('updated_at', today())->count(),
                'total_revenue' => Booking::whereIn('status', ['completed', 'invoiced'])->sum('grand_total'),
                'low_stock_parts' => Sparepart::where('stock', '<=', 5)->count(),
            ];

            $bookings = Booking::with(['customer', 'mechanic', 'service'])
                ->latest()
                ->limit(20)
                ->get();

            $mechanics = User::where('role', 'mekanik')->get();
            $services = Service::active()->get();
            $spareparts = Sparepart::active()->orderBy('name')->get();
        } else {
            // Customer: hanya lihat booking sendiri
            $stats = [
                'total_bookings' => $user->customerBookings()->count(),
                'pending_bookings' => $user->customerBookings()->where('status', 'pending')->count(),
                'in_progress' => $user->customerBookings()->where('status', 'in_progress')->count(),
                'completed' => $user->customerBookings()->whereIn('status', ['completed', 'invoiced'])->count(),
            ];

            $bookings = $user->customerBookings()
                ->with(['mechanic', 'service'])
                ->latest()
                ->get();

            $mechanics = collect();
            $services = Service::active()->get();
            $spareparts = collect();
        }

        return view('dashboard', compact('stats', 'bookings', 'mechanics', 'services', 'spareparts'));
    }
}
