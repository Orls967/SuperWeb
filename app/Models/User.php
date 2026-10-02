<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\AutoDex\Domain\Models\Car;
use Modules\Banking\Domain\Traits\HasLedgerAccounts;
use Modules\Core\Domain\Models\Vehicle;
use Modules\Logistics\Domain\Models\HubOperator;
use Modules\Resto\Domain\Models\RestoStaffAssignment;

#[Fillable(['name', 'email', 'password', 'phone', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasLedgerAccounts, Notifiable;

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

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function isKitchen(): bool
    {
        return $this->role === 'kitchen';
    }

    public function isOutletManager(): bool
    {
        return $this->role === 'outlet_manager';
    }

    public function isTenant(): bool
    {
        return $this->role === 'tenant';
    }

    public function isMallAdmin(): bool
    {
        return $this->role === 'mall_admin';
    }

    public function isTechnician(): bool
    {
        return $this->role === 'technician';
    }

    public function isLogisticsAdmin(): bool
    {
        return $this->role === 'logistics_admin';
    }

    public function isDispatcher(): bool
    {
        return $this->role === 'dispatcher';
    }

    public function isHubOperator(): bool
    {
        return $this->role === 'hub_operator';
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isShipper(): bool
    {
        return $this->role === 'shipper';
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [
            'admin',
            'mekanik',
            'cashier',
            'kitchen',
            'outlet_manager',
            'mall_admin',
            'technician',
            'logistics_admin',
            'dispatcher',
            'hub_operator',
            'driver',
        ]);
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

    // --- Core Vehicles Relationship ---
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class, 'user_id');
    }

    public function activeVehicles(): HasMany
    {
        return $this->vehicles()->where('status', 'active');
    }

    // --- AutoDex Relationships ---

    /** Mobil yang dimiliki user (My Garage) */
    public function garageCars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'core_vehicles', 'user_id', 'car_id')
            ->wherePivotNull('deleted_at')
            ->withPivot(['id', 'uuid', 'plate_number', 'color', 'vin', 'odometer_km', 'status', 'created_at'])
            ->withTimestamps();
    }

    /** Mobil impian user (Wishlist) */
    public function wishlistCars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'dex_wishlists')
            ->withPivot(['priority', 'notes', 'created_at'])
            ->withTimestamps();
    }

    /** Cek apakah mobil ada di garasi user */
    public function hasInGarage(int $carId): bool
    {
        return $this->vehicles()
            ->where('car_id', $carId)
            ->where('status', 'active')
            ->exists();
    }

    /** Cek apakah mobil ada di wishlist user */
    public function hasInWishlist(int $carId): bool
    {
        return $this->wishlistCars()->where('car_id', $carId)->exists();
    }

    public function restoAssignments(): HasMany
    {
        return $this->hasMany(RestoStaffAssignment::class, 'user_id');
    }

    public function assignedOutletId(): ?int
    {
        return $this->restoAssignments()->where('is_active', true)->value('outlet_id');
    }

    public function hubOperatorAssignment(): HasOne
    {
        return $this->hasOne(HubOperator::class, 'user_id');
    }

    public function assignedHubId(): ?int
    {
        return $this->hubOperatorAssignment()->where('is_active', true)->value('hub_id');
    }

    /** @var array<string> */
    protected array $currentAccessTokenAbilities = ['*'];

    /**
     * @param  array<string>  $abilities
     */
    public function withAccessTokenAbilities(array $abilities): self
    {
        $this->currentAccessTokenAbilities = $abilities;

        return $this;
    }

    public function tokenCan(string $ability): bool
    {
        return in_array('*', $this->currentAccessTokenAbilities, true)
            || in_array($ability, $this->currentAccessTokenAbilities, true);
    }
}
