<?php

declare(strict_types=1);

namespace Modules\Core\Http\Controllers;

use App\Models\Booking;
use App\Models\Service;
use App\Models\Sparepart;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Application\Services\ActivityLogger;
use Modules\Core\Application\Services\NotificationService;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Store\Domain\Models\Order;

class DashboardController extends Controller
{
    public function __construct(
        private NotificationService $notifications,
        private ActivityLogger $activity,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return $this->adminDashboard($user);
        }

        if ($user->isMekanik()) {
            return $this->mekanikDashboard($user);
        }

        return $this->customerDashboard($user);
    }

    /**
     * Admin Dashboard: Platform-wide overview.
     */
    private function adminDashboard(User $user): View
    {
        $stats = [
            'total_bookings' => Booking::count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'in_progress' => Booking::where('status', 'in_progress')->count(),
            'completed_today' => Booking::where('status', 'completed')
                ->whereDate('updated_at', today())->count(),
            'total_revenue' => Booking::whereIn('status', ['completed', 'invoiced'])->sum('grand_total'),
            'low_stock_parts' => Sparepart::where('stock', '<=', 5)->count(),
            'total_users' => User::count(),
            'total_customers' => User::where('role', 'customer')->count(),
            'total_vehicles' => Vehicle::where('status', 'active')->count(),
            'total_orders' => Order::count(),
            'pending_orders' => Order::where('status', 'pending')->count(),
        ];

        $bookings = Booking::with(['customer', 'mechanic', 'service'])
            ->latest()
            ->limit(10)
            ->get();

        $recentActivities = $this->activity->recentAll(10);
        $unreadNotifications = $this->notifications->recent($user->id, 5);

        $mechanics = User::where('role', 'mekanik')->get();
        $services = Service::active()->get();
        $spareparts = Sparepart::active()->orderBy('name')->get();

        return view('core::dashboard.admin', compact(
            'stats', 'bookings', 'recentActivities', 'unreadNotifications',
            'mechanics', 'services', 'spareparts'
        ));
    }

    /**
     * Mekanik Dashboard: Job queue + assigned bookings.
     */
    private function mekanikDashboard(User $user): View
    {
        $stats = [
            'total_bookings' => Booking::count(),
            'pending_bookings' => Booking::where('status', 'pending')->count(),
            'in_progress' => Booking::where('status', 'in_progress')->count(),
            'my_active' => $user->mechanicBookings()->where('status', 'in_progress')->count(),
            'my_completed_today' => $user->mechanicBookings()
                ->where('status', 'completed')
                ->whereDate('updated_at', today())->count(),
            'total_revenue' => Booking::whereIn('status', ['completed', 'invoiced'])->sum('grand_total'),
            'low_stock_parts' => Sparepart::where('stock', '<=', 5)->count(),
        ];

        $myBookings = $user->mechanicBookings()
            ->with(['customer', 'service'])
            ->whereIn('status', ['in_progress', 'confirmed'])
            ->latest()
            ->limit(10)
            ->get();

        $pendingBookings = Booking::with(['customer', 'service'])
            ->where('status', 'pending')
            ->latest()
            ->limit(10)
            ->get();

        $recentActivities = $this->activity->recentForModule('autoserve', 10);
        $unreadNotifications = $this->notifications->recent($user->id, 5);

        $bookings = Booking::with(['customer', 'mechanic', 'service'])
            ->latest()
            ->limit(20)
            ->get();

        $mechanics = User::where('role', 'mekanik')->get();
        $services = Service::active()->get();
        $spareparts = Sparepart::active()->orderBy('name')->get();

        return view('core::dashboard.mekanik', compact(
            'stats', 'myBookings', 'pendingBookings', 'bookings',
            'recentActivities', 'unreadNotifications',
            'mechanics', 'services', 'spareparts'
        ));
    }

    /**
     * Customer Dashboard: Personal overview across all modules.
     */
    private function customerDashboard(User $user): View
    {
        $stats = [
            'total_bookings' => $user->customerBookings()->count(),
            'pending_bookings' => $user->customerBookings()->where('status', 'pending')->count(),
            'in_progress' => $user->customerBookings()->where('status', 'in_progress')->count(),
            'completed' => $user->customerBookings()->whereIn('status', ['completed', 'invoiced'])->count(),
            'vehicles_count' => $user->vehicles()->where('status', 'active')->count(),
            'wishlist_count' => $user->wishlistCars()->count(),
            'orders_count' => Order::where('user_id', $user->id)->count(),
        ];

        $bookings = $user->customerBookings()
            ->with(['mechanic', 'service'])
            ->latest()
            ->limit(5)
            ->get();

        $vehicles = $user->vehicles()
            ->where('status', 'active')
            ->with('car')
            ->latest()
            ->limit(5)
            ->get();

        $recentOrders = Order::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        $recentActivities = $this->activity->recentForUser($user->id, 10);
        $unreadNotifications = $this->notifications->recent($user->id, 5);

        $services = Service::active()->get();
        $spareparts = collect();
        $mechanics = collect();

        return view('core::dashboard.customer', compact(
            'stats', 'bookings', 'vehicles', 'recentOrders',
            'recentActivities', 'unreadNotifications',
            'services', 'spareparts', 'mechanics'
        ));
    }
}
