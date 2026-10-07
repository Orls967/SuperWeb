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
        'discount_rate',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'loyalty_points' => 'integer',
        'discount_rate' => 'decimal:2',
        'expires_at' => 'datetime',
    ];
}
