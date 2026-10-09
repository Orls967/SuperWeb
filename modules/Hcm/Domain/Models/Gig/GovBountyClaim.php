<?php

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GovBountyClaim extends Model
{
    protected $table = 'gov_bounty_claims';

    protected $guarded = [];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function bounty(): BelongsTo
    {
        return $this->belongsTo(GovBounty::class, 'bounty_id');
    }

    public function pof(): HasOne
    {
        return $this->hasOne(GovBountyPof::class, 'bounty_claim_id');
    }
}
