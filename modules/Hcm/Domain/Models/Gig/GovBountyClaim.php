<?php

declare(strict_types=1);

namespace Modules\Hcm\Domain\Models\Gig;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GovBountyClaim extends Model
{
    protected $table = 'gov_bounty_claims';

    protected $fillable = [
        'claim_code',
        'bounty_id',
        'employee_id',
        'claimed_at',
        'status',
    ];

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
