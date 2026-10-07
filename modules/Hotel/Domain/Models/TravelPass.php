<?php

declare(strict_types=1);

namespace Modules\Hotel\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TravelPass extends Model
{
    protected $table = 'htl_travel_passes';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'pass_number',
        'user_id',
        'tier',
        'cumulative_nights',
        'cumulative_spend_idr',
        'pts_balance',
    ];

    public function pointsLedger(): HasMany
    {
        return $this->hasMany(TravelPassPointsEntry::class, 'travel_pass_id');
    }
}
