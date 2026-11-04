<?php

declare(strict_types=1);

namespace Modules\Governance\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GovVote extends Model
{
    protected $table = 'gov_votes';

    protected $fillable = [
        'proposal_id',
        'voter_user_id',
        'vote_choice',
        'weight_cast',
        'previous_vote_hash',
        'vote_receipt_hash',
        'merkle_proof',
    ];

    protected $casts = [
        'weight_cast' => 'integer',
        'merkle_proof' => 'array',
    ];

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(GovProposal::class, 'proposal_id');
    }
}
