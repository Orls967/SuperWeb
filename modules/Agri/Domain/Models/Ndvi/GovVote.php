<?php

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovVote extends Model
{
    protected $table = 'gov_votes';

    protected $guarded = [];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(GovProposal::class, 'proposal_id');
    }
}
