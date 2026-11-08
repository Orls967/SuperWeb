<?php

declare(strict_types=1);

namespace Modules\Aviation\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AviationCareVoucher extends Model
{
    protected $table = 'avi_care_vouchers';

    protected $fillable = [
        'disruption_passenger_id',
        'voucher_code',
        'voucher_type',
        'nominal_amount_minor_unit',
        'currency',
        'is_redeemed',
        'redeemed_at',
        'merchant_code',
        'expires_at',
    ];

    protected $casts = [
        'nominal_amount_minor_unit' => 'integer',
        'is_redeemed' => 'boolean',
        'redeemed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function disruptionPassenger(): BelongsTo
    {
        return $this->belongsTo(AviationDisruptionPassenger::class, 'disruption_passenger_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
