<?php

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovBountyPof extends Model
{
    protected $table = 'gov_bounty_pofs';

    protected $guarded = [];

    protected $casts = [
        'actual_hours_worked' => 'decimal:2',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(GovBountyClaim::class, 'bounty_claim_id');
    }
}
