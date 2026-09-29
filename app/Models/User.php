<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // --- Role Helpers ---
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMekanik(): bool
    {
        return $this->role === 'mekanik';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'mekanik']);
    }

    // --- AutoServe Relationships ---

    /** Booking yang dibuat oleh customer ini */
    public function customerBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'customer_id');
    }

    /** Booking yang ditangani oleh mekanik ini */
    public function mechanicBookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'mechanic_id');
    }

    // --- AutoDex Relationships ---

    /** Mobil yang dimiliki user (My Garage) */
    public function garageCars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'garages')
            ->withPivot(['plate_number', 'color', 'year_bought', 'nickname', 'notes'])
            ->withTimestamps();
    }

    /** Mobil impian user (Wishlist) */
    public function wishlistCars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'wishlists')
            ->withPivot(['priority', 'notes'])
            ->withTimestamps();
    }

    /** Cek apakah mobil ada di garasi user */
    public function hasInGarage(int $carId): bool
    {
        return $this->garageCars()->where('car_id', $carId)->exists();
    }

    /** Cek apakah mobil ada di wishlist user */
    public function hasInWishlist(int $carId): bool
    {
        return $this->wishlistCars()->where('car_id', $carId)->exists();
    }
}
