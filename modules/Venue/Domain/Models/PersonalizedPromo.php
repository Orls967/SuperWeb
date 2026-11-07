<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalizedPromo extends Model
{
    protected $table = 'ven_personalized_promos';

    protected $fillable = [
        'promo_dispatch_code',
        'user_id',
        'zone_id',
        'promo_title',
        'discount_percent',
        'discount_bps',
        'floor_price_idr',
        'expires_at',
        'status',
        'delivered_at',
        'redeemed_at',
    ];

    protected $casts = [
        'discount_percent' => 'integer',
        'discount_bps' => 'integer',
        'floor_price_idr' => 'integer',
        'expires_at' => 'datetime',
        'delivered_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
