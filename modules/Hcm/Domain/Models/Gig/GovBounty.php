<?php

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovBounty extends Model
{
    protected $table = 'gov_bounties';

    protected $guarded = [];

    protected $casts = [
        'shift_start' => 'datetime',
        'shift_end' => 'datetime',
        'duration_hours' => 'decimal:2',
    ];

    public function claims(): HasMany
    {
        return $this->hasMany(GovBountyClaim::class, 'bounty_id');
    }
}
