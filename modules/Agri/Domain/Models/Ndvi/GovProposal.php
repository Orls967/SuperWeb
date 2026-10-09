<?php

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GovProposal extends Model
{
    protected $table = 'gov_proposals';

    protected $guarded = [];

    protected $casts = [
        'action_payload' => 'array',
    ];

    public function votes(): HasMany
    {
        return $this->hasMany(GovVote::class, 'proposal_id');
    }
}
