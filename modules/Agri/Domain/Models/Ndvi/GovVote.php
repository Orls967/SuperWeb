<?php

declare(strict_types=1);

namespace Modules\Agri\Domain\Models\Ndvi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovVote extends Model
{
    protected $table = 'agri_votes';

    protected $fillable = [
        'proposal_id',
        'voter_user_id',
        'vote_choice',
        'weight_cast',
        'previous_vote_hash',
        'vote_receipt_hash',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(GovProposal::class, 'proposal_id');
    }
}
