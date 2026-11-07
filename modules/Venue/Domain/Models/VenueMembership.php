<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;

class VenueMembership extends Model
{
    protected $table = 'ven_memberships';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'membership_number',
        'tier',
        'loyalty_points',
        'spend_365_days_idr',
        'visits_365_days',
        'bottle_locker_number',
        'grace_period_expires_at',
        'discount_rate',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'spend_365_days_idr' => 'integer',
        'visits_365_days' => 'integer',
        'discount_rate' => 'decimal:2',
        'grace_period_expires_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
