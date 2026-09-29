<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\Request;

class GarageController extends Controller
{
    /**
     * Tampilkan isi garasi user (My Garage & Wishlist)
     */
    public function index()
    {
        $user = auth()->user();
        $garageCars = $user->garageCars()->with('brand')->latest('pivot_created_at')->get();
        $wishlistCars = $user->wishlistCars()->with('brand')->latest('pivot_created_at')->get();

        return view('autodex.garage', compact('garageCars', 'wishlistCars'));
    }

    /**
     * Tambah/Hapus dari My Garage (Toggle)
     */
    public function toggleGarage(Request $request, Car $car)
    {
        $user = auth()->user();

        if ($user->hasInGarage($car->id)) {
            // Hapus dari garasi
            $user->garageCars()->detach($car->id);
            $message = 'Mobil dihapus dari My Garage.';
            $action = 'removed';
        } else {
            // Tambah ke garasi (opsional: tambah plat nomor via request)
            $user->garageCars()->attach($car->id, [
                'plate_number' => $request->plate_number,
                'color' => $request->color,
                'year_bought' => $request->year_bought,
                'nickname' => $request->nickname,
            ]);
            
            // Jika mobil ditambah ke garasi, otomatis hapus dari wishlist jika ada
            if ($user->hasInWishlist($car->id)) {
                $user->wishlistCars()->detach($car->id);
            }

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
