<?php

namespace Modules\Venue\Domain\Models;

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
        'expires_at',
        'status',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(EntertainmentZone::class, 'zone_id');
    }
}
