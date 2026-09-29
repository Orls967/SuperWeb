<?php

declare(strict_types=1);

namespace Modules\AutoDex\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Core\Contracts\AcquiresVehicle;

class GarageController extends Controller
{
    /**
     * Tampilkan isi garasi user (My Garage & Wishlist)
     */
    public function index()
    {
        $user = auth()->user();
        $garageCars = $user->garageCars()->with('brand')->latest('core_vehicles.created_at')->get();
        $wishlistCars = $user->wishlistCars()->with('brand')->latest('dex_wishlists.created_at')->get();

        return view('autodex.garage', compact('garageCars', 'wishlistCars'));
    }

    /**
     * Tambah/Hapus dari My Garage (Toggle)
     */
    public function toggleGarage(Request $request, Car $car)
    {
        $user = auth()->user();

        if ($user->hasInGarage($car->id)) {
            // Hapus dari garasi (soft delete vehicle)
            $vehicle = $user->vehicles()->where('car_id', $car->id)->where('status', 'active')->first();
            if ($vehicle) {
                $vehicle->delete();
            }
            $message = 'Mobil dihapus dari My Garage.';
            $action = 'removed';
        } else {
            // Tambah ke garasi via AcquiresVehicle contract
            app(AcquiresVehicle::class)->handle(
                user: $user,
                car: $car,
                plateNumber: $request->plate_number,
                color: $request->color,
                odometerKm: (int) ($request->odometer_km ?? 0),
                acquiredViaType: 'manual',
            );

            $message = 'Mobil ditambahkan ke My Garage.';
            $action = 'added';
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'action' => $action]);
        }

        return back()->with('success', $message);
    }

    /**
     * Tambah/Hapus dari Wishlist (Toggle)
     */
    public function toggleWishlist(Request $request, Car $car)
    {
        $user = auth()->user();

        // Jangan izinkan wishlist mobil yang sudah dimiliki
        if ($user->hasInGarage($car->id)) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Mobil sudah ada di garasi Anda.', 'action' => 'error'], 400);
            }

            return back()->with('error', 'Mobil sudah ada di garasi Anda.');
        }

        if ($user->hasInWishlist($car->id)) {
            $user->wishlistCars()->detach($car->id);
            $message = 'Mobil dihapus dari Wishlist.';
            $action = 'removed';
        } else {
            $user->wishlistCars()->attach($car->id);
            $message = 'Mobil ditambahkan ke Wishlist.';
            $action = 'added';
        }

        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'action' => $action]);
        }

        return back()->with('success', $message);
    }
}
