<?php

declare(strict_types=1);

namespace Modules\Logistics\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarrierPayment extends LogisticsEntity
{
    protected $table = 'lgx_carrier_payments';

    protected $fillable = ['carrier_id', 'amount_idr', 'leg_count', 'week_key', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return [
            'amount_idr' => 'integer',
            'leg_count' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class, 'carrier_id');
    }

    public function legs(): HasMany
    {
        return $this->hasMany(ShipmentLeg::class, 'carrier_payment_id');
    }
}
