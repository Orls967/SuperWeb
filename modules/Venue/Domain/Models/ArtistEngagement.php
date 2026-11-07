<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArtistEngagement extends Model
{
    protected $table = 'ven_artist_engagements';

    protected $fillable = [
        'event_id',
        'artist_name',
        'management_company',
        'contract_number',
        'deal_type',
        'guarantee_idr',
        'door_share_bps',
        'wht_bps',
        'advance_paid_idr',
        'gross_pay_idr',
        'wht_idr',
        'net_paid_idr',
        'currency',
        'valas_minor_cents',
        'exchange_rate_idr',
        'status',
        'actor_id',
    ];

    protected $casts = [
        'guarantee_idr' => 'integer',
        'door_share_bps' => 'integer',
        'wht_bps' => 'integer',
        'advance_paid_idr' => 'integer',
        'gross_pay_idr' => 'integer',
        'wht_idr' => 'integer',
        'net_paid_idr' => 'integer',
        'valas_minor_cents' => 'integer',
        'exchange_rate_idr' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(EntertainmentEvent::class, 'event_id');
    }

    public function riders(): HasMany
    {
        return $this->hasMany(ArtistRider::class, 'engagement_id');
    }
}
