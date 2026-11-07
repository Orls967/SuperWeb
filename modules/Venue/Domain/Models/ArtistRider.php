<?php

declare(strict_types=1);

namespace Modules\Venue\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArtistRider extends Model
{
    protected $table = 'ven_artist_riders';

    protected $fillable = [
        'engagement_id',
        'rider_category',
        'description',
        'budget_idr',
        'actual_spent_idr',
        'is_fulfilled',
        'receipt_reference',
    ];

    protected $casts = [
        'budget_idr' => 'integer',
        'actual_spent_idr' => 'integer',
        'is_fulfilled' => 'boolean',
    ];

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(ArtistEngagement::class, 'engagement_id');
    }
}
