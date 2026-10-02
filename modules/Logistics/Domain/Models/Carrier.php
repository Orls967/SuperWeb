<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Carrier extends LogisticsEntity
{
    protected $table = 'lgx_carriers';

    protected $fillable = ['code', 'name', 'mode', 'payment_terms_days', 'is_active'];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function legs(): HasMany
    {
        return $this->hasMany(ShipmentLeg::class, 'carrier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CarrierPayment::class, 'carrier_id');
    }
}
